(function () {

    document.querySelectorAll('table.resizable').forEach(table => {

        const id = table.dataset.gridId || 'grid';

        table.querySelectorAll('th').forEach((th, i) => {

            const key =
                'oscar.colwidth.' +
                id +
                '.' +
                (th.dataset.col || i);

            const saved = localStorage.getItem(key);

            if (saved) {
                th.style.width = saved + 'px';
            }

            const handle = th.querySelector('.resize-handle');

            if (!handle) {
                return;
            }

            handle.addEventListener('mousedown', e => {

                e.preventDefault();
                e.stopPropagation();

                const startX = e.pageX;
                const startW = th.offsetWidth;

                function move(ev) {

                    const w = Math.max(
                        45,
                        startW + ev.pageX - startX
                    );

                    th.style.width = w + 'px';

                    localStorage.setItem(
                        key,
                        String(w)
                    );
                }

                function up() {

                    document.removeEventListener(
                        'mousemove',
                        move
                    );

                    document.removeEventListener(
                        'mouseup',
                        up
                    );
                }

                document.addEventListener(
                    'mousemove',
                    move
                );

                document.addEventListener(
                    'mouseup',
                    up
                );
            });
        });
    });

    document.querySelectorAll('.lookup').forEach(box => {

        const hidden =
            box.querySelector('input[type=hidden]');

        const input =
            box.querySelector('.lookup-search');

        const results =
            box.querySelector('.lookup-results');

        let timer = null;

        // Request token (1.23): ignores obsolete AJAX responses. A fetch started by
        // an earlier keystroke could resolve AFTER a suggestion was clicked and reopen
        // the list. requestId is incremented on every search() call AND on every
        // selection, which invalidates any request still in flight.
        let requestId = 0;

        function search() {

            const q = input.value.trim();

            // token of this request, captured before the (asynchronous) fetch
            const myRequestId = ++requestId;

            fetch(
                'lookup.php?table=' +
                encodeURIComponent(box.dataset.table) +
                '&value=' +
                encodeURIComponent(box.dataset.value) +
                '&label=' +
                encodeURIComponent(box.dataset.label) +
                '&q=' +
                encodeURIComponent(q)
            )
            .then(r => r.json())
            .then(items => {

                // a newer request (keystroke) or a selection happened since: stale
                // response, ignored.
                if (myRequestId !== requestId) {
                    return;
                }

                results.innerHTML = '';

                items.forEach(it => {

                    const d =
                        document.createElement('div');


                    d.textContent = it.label;

                    d.onclick = () => {

                        // invalidate any request still in flight (see requestId above), so that
                        // a late response cannot reopen the list right after the selection.
                        requestId++;

                        hidden.value = it.id;
                        input.value = it.label;

                        results.style.display = 'none';
                    };

                    results.appendChild(d);
                });

                results.style.display =
                    items.length ? 'block' : 'none';
            });
        }

        input.addEventListener('input', () => {

            hidden.value = '';

            clearTimeout(timer);

            timer = setTimeout(
                search,
                180
            );
        });

        // Search on focus only when nothing is selected yet (hidden empty, 1.23):
        // otherwise clicking back into the field reopened the list. To change a
        // selected value, the user just types (the 'input' listener above empties
        // hidden.value and searches again).
        input.addEventListener('focus', () => {
            if (hidden.value === '') {
                search();
            }
        });

        document.addEventListener('click', e => {

            if (!box.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    });

})();
