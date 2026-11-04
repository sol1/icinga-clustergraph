(function (Icinga) {


    var Clustergraph = function (module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('Clustergraph module loaded');
    };

    function rgbToHex(rgb) {
        const match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) {
            return null;
        }
        const toHex = (n) => ('0' + parseInt(n).toString(16)).slice(-2);
        return `#${toHex(match[1])}${toHex(match[2])}${toHex(match[3])}`;
    }

    function stateColor(node) {
        if (node.state === null || node.state === undefined) return "#dbdbdb"; // unknown host
        if (!node.last_check || node.last_check <= 0) return "#77aaff";        // Pending
        if (node.state === 0) return "#44bb77";                                // Up
        if (node.state === 1) return "#ff5566";                                // Down
        return "#dbdbdb";
    }

    function stateName(node) {
        if (node.state === null || node.state === undefined) return "unknown";
        if (!node.last_check || node.last_check <= 0) return "pending";
        return node.state === 0 ? "up" : "down";
    }

    /**
     * Lay out a directed acyclic graph in columns (parents left, children right).
     *
     * Each node's column is the length of the longest parent chain above it, so a
     * host with several parents always sits to the right of all of them. Rows are
     * ordered with a few barycenter sweeps to reduce crossing edges.
     */
    function layout(nodes, links) {
        const byName = new Map(nodes.map(n => [n.name, n]));
        const parents = new Map(nodes.map(n => [n.name, []]));
        const children = new Map(nodes.map(n => [n.name, []]));
        const indegree = new Map(nodes.map(n => [n.name, 0]));

        links.forEach(l => {
            if (!byName.has(l.source) || !byName.has(l.target)) return;
            children.get(l.source).push(l.target);
            parents.get(l.target).push(l.source);
            indegree.set(l.target, indegree.get(l.target) + 1);
        });

        // Longest-path layering via Kahn's topological sort
        const layer = new Map(nodes.map(n => [n.name, 0]));
        const queue = nodes.filter(n => indegree.get(n.name) === 0).map(n => n.name);
        const seen = new Set();
        while (queue.length) {
            const name = queue.shift();
            seen.add(name);
            children.get(name).forEach(child => {
                layer.set(child, Math.max(layer.get(child), layer.get(name) + 1));
                indegree.set(child, indegree.get(child) - 1);
                if (indegree.get(child) === 0) queue.push(child);
            });
        }
        // Icinga rejects dependency cycles, but don't hang or drop nodes if one slips through
        nodes.forEach(n => {
            if (!seen.has(n.name)) {
                const maxParent = Math.max(-1, ...parents.get(n.name).filter(p => seen.has(p)).map(p => layer.get(p)));
                layer.set(n.name, maxParent + 1);
            }
        });

        const layerCount = Math.max(0, ...layer.values()) + 1;
        const columns = Array.from({length: layerCount}, () => []);
        nodes
            .slice()
            .sort((a, b) => d3.ascending(a.label, b.label))
            .forEach(n => columns[layer.get(n.name)].push(n.name));

        const position = new Map();
        const reindex = () => columns.forEach(col => col.forEach((name, i) => position.set(name, i)));
        reindex();

        const barycenter = (name, neighbours) => {
            const list = neighbours.get(name);
            if (!list.length) return position.get(name);
            return d3.mean(list, n => position.get(n));
        };

        for (let sweep = 0; sweep < 4; sweep++) {
            for (let i = 1; i < layerCount; i++) {
                columns[i].sort((a, b) => barycenter(a, parents) - barycenter(b, parents));
                columns[i].forEach((name, idx) => position.set(name, idx));
            }
            for (let i = layerCount - 2; i >= 0; i--) {
                columns[i].sort((a, b) => barycenter(a, children) - barycenter(b, children));
                columns[i].forEach((name, idx) => position.set(name, idx));
            }
        }

        return {columns, layer, position, layerCount};
    }

    Clustergraph.prototype = {

        initialize: function () {
            this.module.on('rendered', this.onRenderedContainer);
        },

        chart: function (data) {
            const navigation = document.getElementById("navigation");
            const textColor = (navigation && rgbToHex(getComputedStyle(navigation).getPropertyValue('color').trim())) || "#535353";

            const nodes = data.nodes || [];
            const links = data.links || [];

            if (!nodes.length) {
                const empty = document.createElement('p');
                empty.textContent = 'No hosts found in the "synthetic" host group.';
                return empty;
            }

            const {columns, layer, position, layerCount} = layout(nodes, links);

            const rowHeight = 30;
            const padding = 20;
            const labelOffset = 12; // circle centre to the end of its label (labels sit left of the dot)
            const columnGap = 110;  // space left for the edges between columns
            const maxRows = Math.max(...columns.map(c => c.length));

            // Measure labels so each column leaves room for its text
            const ctx = document.createElement("canvas").getContext("2d");
            const labelWidth = new Map(nodes.map(n => {
                ctx.font = (n.synthetic ? "bold" : "italic") + " 14px sans-serif";
                return [n.name, ctx.measureText(n.label).width];
            }));

            // Children on the left, parents on the right: lay columns out from the
            // deepest layer to layer 0. Each column is as wide as its longest label
            // (drawn left of the dot) plus the gap for edges.
            const columnX = new Array(layerCount);
            let x = padding;
            for (let col = layerCount - 1; col >= 0; col--) {
                x += Math.max(0, ...columns[col].map(name => labelWidth.get(name))) + labelOffset;
                columnX[col] = x;
                x += columnGap;
            }

            // Centre each column vertically against the tallest one
            const point = name => {
                const col = layer.get(name);
                const offset = (maxRows - columns[col].length) * rowHeight / 2;
                return {
                    x: columnX[col],
                    y: padding + offset + position.get(name) * rowHeight
                };
            };
            // Edges leave from the far (left) end of the parent's label and run left
            // into the child's dot from the right, so they never cross a label.
            const edgeStart = name => {
                const p = point(name);
                return {x: p.x - labelOffset - labelWidth.get(name) - 6, y: p.y};
            };

            const width = x - columnGap + padding;
            const height = padding * 2 + (maxRows - 1) * rowHeight + 10;

            const svg = d3.create("svg")
                .attr("width", width)
                .attr("height", height)
                .attr("viewBox", [0, 0, width, height])
                .attr("style", "max-width: 100%; height: auto; font: 14px sans-serif;");

            svg.append("defs").append("marker")
                .attr("id", "clustergraph-arrow")
                .attr("viewBox", "0 -5 10 10")
                .attr("refX", 14)
                .attr("refY", 0)
                .attr("markerWidth", 6)
                .attr("markerHeight", 6)
                .attr("orient", "auto")
                .append("path")
                .attr("d", "M0,-5L10,0L0,5")
                .attr("fill", "#00c3ed");

            const nodeNames = new Set(nodes.map(n => n.name));
            const drawnLinks = links.filter(l => nodeNames.has(l.source) && nodeNames.has(l.target));

            svg.append("g")
                .attr("fill", "none")
                .attr("stroke", "#00c3ed")
                .attr("stroke-opacity", 0.5)
                .attr("stroke-width", 2)
                .selectAll("path")
                .data(drawnLinks)
                .join("path")
                .attr("marker-end", "url(#clustergraph-arrow)")
                .attr("d", d3.linkHorizontal()
                    .source(l => edgeStart(l.source))
                    .target(l => point(l.target))
                    .x(p => p.x)
                    .y(p => p.y));

            const node = svg.append("g")
                .selectAll("a")
                .data(nodes)
                .join("a")
                .attr("href", d => d.link)
                .attr("transform", d => {
                    const p = point(d.name);
                    return `translate(${p.x},${p.y})`;
                });

            node.append("title")
                .text(d => [
                    d.name,
                    "state: " + stateName(d),
                    d.zone ? "zone: " + d.zone : null,
                    d.synthetic ? null : "not in the synthetic host group"
                ].filter(Boolean).join("\n"));

            node.append("circle")
                .attr("r", 6)
                .attr("fill", d => stateColor(d))
                .attr("stroke", d => d.synthetic ? "none" : textColor)
                .attr("stroke-dasharray", "2,2");

            node.append("text")
                .attr("x", -labelOffset)
                .attr("dy", "0.32em")
                .attr("text-anchor", "end")
                .attr("fill", d => d.synthetic ? stateColor(d) : textColor)
                .attr("opacity", d => d.synthetic ? 1 : 0.6)
                .attr("font-weight", d => d.synthetic ? "bold" : "normal")
                .attr("font-style", d => d.synthetic ? "normal" : "italic")
                .text(d => d.label);

            return svg.node();
        },


        onRenderedContainer: function (event) {
            const container = document.getElementById('clustergraph-container');
            // in module configuration we don't have a graph, so return peacefully
            if (!container) {
                return;
            }

            fetch(window.location.pathname + "/index/data")
                .then(response => response.json())
                .then(data => {
                    container.replaceChildren(this.chart(data));
                });


        }
    };

    Icinga.availableModules.clustergraph = Clustergraph;

}(Icinga));
