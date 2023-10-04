(function (Icinga) {


    var Clustergraph = function (module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('Clustergraph module loaded');
    };

    Clustergraph.prototype = {

        initialize: function () {
            this.module.on('rendered', this.onRenderedContainer);
        },

        chart: function (data) {
            const root = d3.hierarchy(data);
            const dx = 40;
            const width = 940;
            const dy = width / (root.height + 1);
            const tree = d3.tree().nodeSize([dx, dy]);
            root.sort((a, b) => d3.ascending(a.data.name, b.data.name));
            tree(root);

            let x0 = Infinity;
            let x1 = -x0;
            root.each(d => {
                if (d.x > x1) x1 = d.x;
                if (d.x < x0) x0 = d.x;
            });

            const height = x1 - x0 + dx * 2;

            const svg = d3.create("svg")
                .attr("width", width)
                .attr("height", height)
                .attr("viewBox", [-dy / 3, x0 - dx, width, height])
                .attr("style", "max-width: 100%; height: auto; font: 10px sans-serif;");

            const link = svg.append("g")
                .attr("fill", "none")
                .attr("stroke", "#555")
                .attr("stroke-opacity", 0.4)
                .attr("stroke-width", 1.5)
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
                .attr("fill", d => d.children ? "#555" : "#999")
                .attr("r", 2);

            node.append("text")
                .attr("dy", "0.5em")
                .attr("x", d => d.children ? -8 : 8)
                .attr("text-anchor", d => d.children ? "end" : "start")
                .text(d => d.data.name)
                .clone(true).lower()
                .attr("stroke", "white");

            // Append endpoint text below the zone name
            node.append("text")
                .attr("dy", "2em")
                .attr("x", d => d.children ? -6 : 6)
                .attr("text-anchor", "middle")
                .attr("fill", "#ffffff")
                .text(d => {
                    const endpointText = d.data.endpoints ? d.data.endpoints.join(", ") : "";
                    return endpointText;
                });

            return svg.node();
        },


        onRenderedContainer: function (event) {
            // in module configuration we don't have a map, so return peacefully

            fetch("/icingaweb2/clustergraph/index/data")
                .then(response => response.json())
                .then(data => {
                    document.getElementById('clustergraph-container').append(this.chart(data));
                });


        }
    };

    Icinga.availableModules.clustergraph = Clustergraph;

}(Icinga));
