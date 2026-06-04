(function () {
    const csscls = PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-');

    /**
     * Widget for displaying model counts with expandable source details.
     * Uses the same ListWidget + hidden table toggle pattern as SQLQueriesWidget.
     *
     * When source tracking is disabled, this widget is not loaded and the
     * default TableVariableListWidget is used instead.
     */
    class ModelsWidget extends PhpDebugBar.Widget {
        get className() {
            return csscls('models');
        }

        renderSourceList(table, sources) {
            const thead = document.createElement('thead');
            const tr = document.createElement('tr');
            const th = document.createElement('th');
            th.colSpan = 2;
            th.classList.add(csscls('name'));
            th.textContent = 'Source';
            tr.append(th);
            thead.append(tr);
            table.append(thead);

            const tbody = document.createElement('tbody');

            for (const source of sources) {
                const row = document.createElement('tr');

                const labelTd = document.createElement('td');
                if (source.xdebug_link) {
                    const link = PhpDebugBar.Widgets.editorLink(source.xdebug_link);
                    link.textContent = source.label;
                    labelTd.append(link);
                } else {
                    labelTd.textContent = source.label;
                }
                row.append(labelTd);

                const countTd = document.createElement('td');
                countTd.classList.add('phpdebugbar-text-muted');
                countTd.textContent = '\u00d7' + source.count;
                row.append(countTd);

                tbody.append(row);
            }

            table.append(tbody);
        }

        itemRenderer(li, model) {
            // Editor link to model class file (right side, like queries)
            if (model.xdebug_link) {
                li.prepend(PhpDebugBar.Widgets.editorLink(model.xdebug_link));
            }

            // Model class name
            const code = document.createElement('span');
            code.classList.add(csscls('models-class'));
            code.textContent = model.class;
            li.append(code);

            // Event count badges
            if (model.counts) {
                for (const [label, count] of Object.entries(model.counts)) {
                    if (!count) continue;
                    const badge = document.createElement('span');
                    badge.classList.add(csscls('models-count'));
                    badge.setAttribute('title', label);
                    badge.textContent = `${label}: ${count}`;
                    li.append(badge);
                }
            }

            // Sources detail table (hidden by default, toggle on click)
            if (model.sources && model.sources.length > 0) {
                const table = document.createElement('table');
                table.classList.add(csscls('params'));
                table.hidden = true;

                this.renderSourceList(table, model.sources);

                li.append(table);
                li.style.cursor = 'pointer';
                li.addEventListener('click', () => {
                    table.hidden = !table.hidden;
                });
            }
        }

        render() {
            this.status = document.createElement('div');
            this.status.classList.add(csscls('status'));
            this.el.append(this.status);

            this.list = new PhpDebugBar.Widgets.ListWidget({
                itemRenderer: (li, model) => this.itemRenderer(li, model),
            });
            this.el.append(this.list.el);

            this.bindAttr('data', function (data) {
                if (!data || !data.data) {
                    return;
                }

                const key_map = data.key_map || {};

                // Transform into list items for the ListWidget
                const items = [];
                for (const [className, values] of Object.entries(data.data)) {
                    if (typeof values !== 'object' || values === null) {
                        continue;
                    }

                    const counts = {};
                    for (const key of Object.keys(key_map)) {
                        if (values[key]) {
                            counts[key_map[key] || key] = values[key];
                        }
                    }

                    items.push({
                        class: className,
                        counts: counts,
                        sources: values.sources || null,
                        xdebug_link: values.xdebug_link || null,
                    });
                }

                this.list.set('data', items);

                // Status bar
                this.status.innerHTML = '';
                const t = document.createElement('span');
                t.textContent = `${items.length} models, ${data.count || 0} events`;
                this.status.append(t);

                if (data.badges) {
                    for (const [key, count] of Object.entries(data.badges)) {
                        const badge = document.createElement('span');
                        badge.classList.add(csscls('models-status-badge'));
                        badge.textContent = `${key_map[key] || key}: ${count}`;
                        this.status.append(badge);
                    }
                }
            });
        }
    }

    PhpDebugBar.Widgets.ModelsWidget = ModelsWidget;
})();
