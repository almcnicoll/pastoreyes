export default function relationshipGraph(
    initialData,
    centralPersonId,
    basePeopleUrl,
) {
    return {
        cy: null,
        currentCentralId: centralPersonId,
        clickTimer: null,
        clickDelay: 250, // ms to wait before deciding single vs double click

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
                            // Currently centred node
                            selector: "node[?isCentral]",
                            style: {
                                "border-width": "3px",
                                "border-color": "#4F46E5",
                                width: "44px",
                                height: "44px",
                            },
                        },
                        {
                            // Profile person node (when graph is re-centred elsewhere)
                            selector: "node[?isProfile]",
                            style: {
                                "border-width": "2px",
                                "border-color": "#9CA3AF",
                                "border-style": "dashed",
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

                // Single click = re-centre graph on node
                // Double click = navigate to person's profile
                this.cy.on("tap", "node", (event) => {
                    const personId = event.target.data("personId");
                    if (!personId) return;

                    if (this.clickTimer) {
                        // Second click within delay = double click
                        clearTimeout(this.clickTimer);
                        this.clickTimer = null;
                        this.navigateToPerson(personId);
                    } else {
                        // Start timer — if no second click, treat as single click
                        this.clickTimer = setTimeout(() => {
                            this.clickTimer = null;
                            if (personId !== this.currentCentralId) {
                                this.currentCentralId = personId;
                                $wire.recenterGraph(personId);
                            }
                        }, this.clickDelay);
                    }
                });
            });
        },

        navigateToPerson(personId) {
            const url = basePeopleUrl + personId;
            // Use Livewire's SPA navigation if available, otherwise fall back to location
            if (window.Livewire && window.Livewire.navigate) {
                window.Livewire.navigate(url);
            } else {
                window.location.href = url;
            }
        },

        refreshGraph(newData) {
            if (!this.cy) return;

            // Update tracked central ID from server response
            if (newData.centralPersonId) {
                this.currentCentralId = newData.centralPersonId;
            }

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
