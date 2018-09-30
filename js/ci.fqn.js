let fqnRoot, allNodes = {}, allLeaves = {};
$(function() {
    const LOCK = {};

    const SORT_NAME_NO_CASE = (a, b) => {
        return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
    };
    const SORT_NAME_CASE = (a, b) => {
        return a.name.localeCompare(b.name);
    };
    const SORT_ID = (a, b) => {
        return a.id > b.id ? 1 : -1;
    };
    const SORT_ID_REVERSE = (a, b) => {
        return b.id > a.id ? 1 : -1;
    };

    let sortingMethod = SORT_NAME_NO_CASE;

    class Node {
        constructor(name, id, parent) {
            this.name = name;
            this.id = id;
            this.parent = parent;
            this.depth = parent !== null ? (parent.depth + 1) : 0;
            this.expanded = false;
            this.branch = null;
            allNodes[this.id] = this
        }

        getFullName() {
            return this.depth === 0 ? "\\" : this.name;
            // switch(this.depth) {
            //     case 0:
            //         return "\\";
            //     case 1:
            //         return this.name;
            //     default:
            //         return this.parent.getFullName() + "\\" + this.name;
            // }
        }

        resort() {
            if(this.branch !== null) {
                this.branch.resort(true);
            }
        }

        toggleExpand() {
            if(this.branch === null) {
                this.branch = LOCK;
                this.$ph.addClass("disabled");
                ajax("fqn.api", {
                    data: this.depth > 0 ? {parent: this.id} : {},
                    success: (data) => {
                        this.branch = new Branch(this, data);
                        this.expanded = true;
                        this.$ph.removeClass("disabled").text("Collapse");
                        this.$br.removeClass("branch-hidden");
                        this.$br.append(this.branch.$());
                    }
                });
                return;
            }
            if(this.branch === LOCK) {
                return;
            }

            if(this.expanded) {
                this.expanded = false;
                this.$ph.text("Expand");
                this.$br.addClass("branch-hidden");
            } else {
                this.expanded = true;
                this.$ph.text("Collapse");
                this.$br.removeClass("branch-hidden");
            }
        }

        $() {
            if(this.$div !== undefined) return this.$div;
            this.$div = $("<div class='node'></div>");
            this.$label = $("<p class='node-label'></p>")
                .append($("<code class='code'></code>").text(this.getFullName()))
                .appendTo(this.$div);
            this.$ph = $("<span class='action'>Expand</span>").appendTo(this.$label);
            this.$br = $("<div class='branch branch-hidden'></div>").appendTo(this.$div);
            this.$ph.click(() => this.toggleExpand());
            this.$div.attr("data-id", this.id);
            return this.$div;
        }
    }

    class Leaf {
        constructor(name, id, projects, builds, parent) {
            this.name = name;
            this.id = id;
            this.projects = projects;
            this.builds = builds;
            this.parent = parent;
            allLeaves[this.id] = this;
        }

        $() {
            if(this.$div !== undefined) return this.$div;
            this.$div = $("<div></div>");
            this.$label = $("<p></p>")
                .append($("<code class='leaf-label code'></code>").text(this.parent.getFullName() + "\\" + this.name))
                .append($("<span class='leaf-usage'></span>").text(`${this.projects} project${this.projects > 1 ? "s" : ""}, ${this.builds} build${this.builds > 1 ? "s" : ""}`))
                .appendTo(this.$div);
            this.$div.attr("data-id", this.id);
            return this.$div;
        }
    }

    class Branch {
        constructor(parent, children) {
            this.parent = parent;
            this.nodes = [];
            this.leaves = [];
            for(let i = 0; i < children.length; ++i) {
                const child = children[i];
                if(child.type === "ns") {
                    this.nodes.push(new Node(child.name, child.id, parent));
                } else if(child.type === "class") {
                    this.leaves.push(new Leaf(child.name, child.id, child.projects, child.builds, parent));
                }
            }
            this.resort(false)
        }

        resort(late) {
            this.nodes.sort(sortingMethod);
            this.leaves.sort(sortingMethod);
            if(late) {
                this.$nodes.children().sortElements((a, b) =>{
                    return sortingMethod(allNodes[a.getAttribute("data-id")], allNodes[b.getAttribute("data-id")]);
                });
                this.$leaves.children().sortElements((a, b) =>{
                    return sortingMethod(allLeaves[a.getAttribute("data-id")], allLeaves[b.getAttribute("data-id")]);
                });
                for(let i = 0; i < this.nodes; ++i) {
                    this.nodes[i].resort();
                }
            }
        }

        $() {
            if(this.$div !== undefined) return this.$div;
            this.$div = $("<div></div>");
            this.$nodes = $("<div></div>").appendTo(this.$div);
            this.$leaves = $("<div></div>").appendTo(this.$div);
            for(let i = 0; i < this.nodes.length; ++i) {
                this.$nodes.append(this.nodes[i].$());
            }
            for(let i = 0; i < this.leaves.length; ++i) {
                this.$leaves.append(this.leaves[i].$());
            }
            return this.$div;
        }
    }

    fqnRoot = new Node("", 0, null);
    $("#tree-container").append(fqnRoot.$());
    fqnRoot.toggleExpand();

    function setSortingMethod(method) {
        sortingMethod = method;
        fqnRoot.resort();
    }

    $("#sort-fqn").change(function(){
        let method;
        switch(this.value){
            case "SORT_NAME_NO_CASE":
                method = SORT_NAME_NO_CASE;
                break;
            case "SORT_NAME_CASE":
                method = SORT_NAME_CASE;
                break;
            case "SORT_ID":
                method = SORT_ID;
                break;
            case "SORT_ID_REVERSE":
                method = SORT_ID_REVERSE;
                break;
        }
        setSortingMethod(method);
    })
});
