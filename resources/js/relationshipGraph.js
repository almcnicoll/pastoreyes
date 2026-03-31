export default function relationshipGraph(
    initialData,
    centralPersonId,
    basePeopleUrl,
) {
    return {
        cy: null,

        init() {
            this.$nextTick(() => {
                this.cy = window.cytoscape({
                    container: document.getElementById("cy"),
                    elements: [...initialData.nodes, ...initialData.edges],
                    style: [
                        {
                            selector: "node",
                            style: {
                                "background-color": "data(color)",
                                label: "data(label)",
                                color: "#1F2937",
                                "font-size": "11px",
                                "text-valign": "bottom",
                                "text-margin-y": "4px",
                                width: "36px",
                                height: "36px",
                                "border-width": 0,
                            },
                        },
                        {
                            selector: "node[?isCentral]",
                            style: {
                                "border-width": "3px",
                                "border-color": "#4F46E5",
                                width: "44px",
                                height: "44px",
                            },
                        },
                        {
                            selector: "edge",
                            style: {
                                width: 2,
                                "line-color": "#D1D5DB",
                                label: "data(label)",
                                "font-size": "10px",
                                color: "#6B7280",
                                "text-rotation": "autorotate",
                                "text-margin-y": "-8px",
                                "curve-style": "bezier",
                            },
                        },
                    ],
                    layout: {
                        name: "cose",
                        animate: false,
                        nodeDimensionsIncludeLabels: true,
                    },
                    userZoomingEnabled: true,
                    userPanningEnabled: true,
                });

                // Navigate to person profile on node click
                this.cy.on("tap", "node", (event) => {
                    const personId = event.target.data("personId");
                    if (personId && personId !== centralPersonId) {
                        window.location.href = basePeopleUrl + personId;
                    }
                });
            });
        },

        refreshGraph(newData) {
            if (!this.cy) return;
            this.cy.elements().remove();
            this.cy.add([...newData.nodes, ...newData.edges]);
            this.cy
                .layout({
                    name: "cose",
                    animate: true,
                    nodeDimensionsIncludeLabels: true,
                })
                .run();
        },
    };
}
