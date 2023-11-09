(function (Icinga) {


    var Clustergraph = function (module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('Clustergraph module loaded');
    };

    function rgbToHex(rgb) {
        const [, r, g, b] = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
        const toHex = (n) => ('0' + parseInt(n).toString(16)).slice(-2);
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    Clustergraph.prototype = {

        initialize: function () {
            this.module.on('rendered', this.onRenderedContainer);
        },

        chart: function (data) {
            const textColor = rgbToHex(getComputedStyle(document.getElementById("navigation")).getPropertyValue('color').trim()) || "#535353";
            const root = d3.hierarchy(data);
            const nodeWidth = 55; // dx value
            const width = 1720;
            const nodeHeight = width / (root.height + 1.2); // dy value
            const padding = 80; // for the text to fit
            const tree = d3.tree().nodeSize([nodeWidth, nodeHeight]);
            root.sort((a, b) => d3.ascending(a.data.name, b.data.name));
            tree(root);

            // x0 -> x1 is the minimum x and maximum x of the nodes of the tree
            // tree is sideways so x is vertical / height and nodeWidth is also vertical etc.
            let x0 = Infinity;
            let x1 = -x0;
            root.each(d => {
                if (d.x > x1) x1 = d.x;
                if (d.x < x0) x0 = d.x;
            });

            const height = x1 - x0 + nodeWidth * 2;

            const svg = d3.create("svg")
                .attr("width", width)
                .attr("height", height)
                .attr("viewBox", [-nodeHeight / 3 - padding, x0 - nodeWidth, width + padding, height + padding])
                .attr("style", "max-width: 100%; height: auto; font: 18px sans-serif;");

            const link = svg.append("g")
                .attr("fill", "none")
                .attr("stroke", "#00c3ed")
                .attr("stroke-opacity", 0.4)
                .attr("stroke-width", 2.5)
                .selectAll()
                .data(root.links())
                .join("path")
                .attr("d", d3.linkHorizontal()
                    .x(d => d.y)
                    .y(d => d.x));

            const node = svg.append("g")
                .attr("stroke-linejoin", "round")
                .attr("stroke-width", 3)
                .selectAll()
                .data(root.descendants())
                .join("g")
                .attr("transform", d => `translate(${d.y},${d.x})`);

            node.append("circle")
                .attr("fill", "#999")
                .attr("r", 3.14);

            node.append("text")
                .attr("dy", "0.30em")
                .attr("x", d => d.children ? -8 : 8)
                .attr("text-anchor", d => d.children ? "end" : "start")
                .attr("fill", d => (d.data.endpoints && d.data.endpoints.length > 0) ? textColor : "#ff33ff")
                .attr("font-weight", "bold")
                .text(d => "zone: " + d.data.name);

            // Append endpoint text below the zone name
            let endpointNode = node.append("text")
                .attr("transform", d => `translate(${d.children ? -16 : 0},0)`)
                .attr("text-anchor", d => d.children ? "end" : "start")
                .attr("x", d => d.children ? -8 : 8)

            endpointNode.each(function (nodeData, i) {
                // If there are endpoints, bind them to the tspan elements
                if (nodeData.data.endpoints && nodeData.data.endpoints.length) {
                    d3.select(this)
                        .selectAll("tspan")
                        .data(nodeData.data.endpoints)
                        .enter()
                        .append("tspan")
                        .attr("x", nodeData => nodeData.children ? -8 : 8)
                        .attr("dy", (endpointData, i) => i === 0 ? "1.45em" : "1em") // Only add spacing after the first tspan
                        .attr("fill", endpointData => {
                            return endpointData.last_check <= 0 ? "#77aaff" :  // Pending
                                endpointData.state === 0 ? "#44bb77" : // Up
                                endpointData.state === 1 ? "#ff5566" : // Down
                                "#dbdbdb"; // default white color
                        })
                        .text(endpointData => endpointData.name);
                }
            });


            return svg.node();
        },


        onRenderedContainer: function (event) {
            // in module configuration we don't have a map, so return peacefully

            fetch(window.location.pathname + "/index/data")
                .then(response => response.json())
                .then(data => {
                    document.getElementById('clustergraph-container').append(this.chart(data));
                });


        }
    };

    Icinga.availableModules.clustergraph = Clustergraph;

}(Icinga));