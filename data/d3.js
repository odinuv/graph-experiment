 var width = 800, height = 800;
  // force layout setup
  var force = d3.layout.force()
          .charge(-200).linkDistance(30).size([width, height]);

  // setup svg div
  var svg = d3.select("#graph").append("svg")
          .attr("width", "100%").attr("height", "100%")
          .attr("pointer-events", "all");

  // load graph (nodes,links) json from /graph endpoint
  d3.json("/graph", function(error, graph) {
if (error) return;

      force.nodes(graph.nodes).links(graph.links).start();

      // render relationships as lines
      var link = svg.selectAll(".link")
              .data(graph.links).enter()
              .append("line").attr("class", "link");

      // render nodes as circles, css-class from label
      var node = svg.selectAll(".node")
              .data(graph.nodes).enter()
              .append("circle")
              .attr("class", function (d) { return "node "+d.label })
              .attr("r", 10)
              .call(force.drag);

      // html title attribute for title node-attribute
      node.append("title")
              .text(function (d) { return d.title; })

      // force feed algo ticks for coordinate computation
      force.on("tick", function() {
          link.attr("x1", function(d) { return d.source.x; })
                  .attr("y1", function(d) { return d.source.y; })
                  .attr("x2", function(d) { return d.target.x; })
                  .attr("y2", function(d) { return d.target.y; });

          node.attr("cx", function(d) { return d.x; })
                  .attr("cy", function(d) { return d.y; });
      });
  });