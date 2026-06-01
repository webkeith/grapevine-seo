/**
 * Grapevine SEO — Admin JS v2.0
 * Handles: SEO gauge chart, distribution chart, run-all, per-page analyzer,
 *          table filter/search, meta box tabs, schema group toggling, char counter.
 */
/* global RAS, GVSEO_SEO, Chart */
(function ($) {
    'use strict';

    /* ═══ SEO ANALYSIS PAGE ══════════════════════════════════════════ */
    if (typeof GVSEO_SEO !== 'undefined') {
        initGauge();
        initDist();
        initRunAll();
        initPageAnalyzer();
        initTable();
    }

    /* ── Gauge Chart ──────────────────────────────────────────────── */
    function initGauge() {
        var ctx = document.getElementById('gvseo-gauge');
        if (!ctx) { return; }
        var avg   = GVSEO_SEO.summary.avg || 0;
        var color = scoreColor(avg);
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [avg, 100 - avg],
                    backgroundColor: [color, '#1a2840'],
                    borderWidth: 0,
                    circumference: 270,
                    rotation: 225,
                }]
            },
            options: {
                cutout: '78%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { duration: 800 }
            }
        });
    }

    /* ── Distribution Donut ───────────────────────────────────────── */
    function initDist() {
        var ctx = document.getElementById('gvseo-dist');
        if (!ctx) { return; }
        var s = GVSEO_SEO.summary;
        var total = s.excellent + s.good + s.needs_work + s.poor;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Needs Work', 'Poor'],
                datasets: [{
                    data: [s.excellent, s.good, s.needs_work, s.poor],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    borderColor: '#0d1117',
                    borderWidth: 2,
                }]
            },
            options: {
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw; } } }
                },
                animation: { duration: 600 }
            }
        });
    }

    /* ── Run All Analysis ─────────────────────────────────────────── */
    function initRunAll() {
        $('#gvseo-run-btn').on('click', function () {
            var $btn = $(this).prop('disabled', true).text('Analyzing…');
            $('#gvseo-prog-wrap').show();
            animateProgress();

            $.post(RAS.ajax, { action: 'gvseo_analyze_all', nonce: RAS.nonce }, function (res) {
                if (!res.success) { alert('Error. Please try again.'); $btn.prop('disabled', false).text('▶ Analyze Entire Site'); return; }

                var s = res.data.summary;
                $('#gvseo-stat-total').text(s.total);
                $('#gvseo-stat-analyzed').text(s.total);
                $('#gvseo-stat-pending').text(0);
                $('#gvseo-last-run').text('just now');
                $('#gvseo-prog-fill').css('width', '100%');
                $('#gvseo-prog-txt').text('✓ Analysis complete — ' + s.total + ' pages analyzed');
                $btn.prop('disabled', false).text('▶ Analyze Entire Site');

                // Update table rows
                updateTableRows(res.data.posts);

                // Reload the page select options
                updatePageSelect(res.data.posts);
            }).fail(function () {
                $btn.prop('disabled', false).text('▶ Analyze Entire Site');
                $('#gvseo-prog-txt').text('Error. Please try again.');
            });
        });
    }

    function animateProgress() {
        var pct = 0;
        var iv = setInterval(function () {
            pct = Math.min(pct + Math.random() * 12, 90);
            $('#gvseo-prog-fill').css('width', pct + '%');
            if (pct >= 90) { clearInterval(iv); }
        }, 300);
    }

    function updateTableRows(posts) {
        posts.forEach(function (p) {
            var $row = $('[data-id="' + p.id + '"]');
            if (!$row.length) { return; }
            $row.attr('data-label', p.label);
            var scoreHtml = p.score !== null
                ? '<div class="gvseo-mini-bar-wrap"><div class="gvseo-mini-bar"><div class="gvseo-mini-fill gvseo-fill-' + p.label + '" style="width:' + p.score + '%"></div></div><strong>' + p.score + '</strong></div>'
                : '<span class="gvseo-muted-dash">—</span>';
            $row.find('td:nth-child(3)').html(scoreHtml);

            var labels = { excellent: '★ Excellent', good: '▲ Good', needs_work: '⚠ Needs Work', poor: '✗ Poor', unanalyzed: '○ Not Analyzed' };
            $row.find('td:nth-child(4)').html('<span class="gvseo-status-badge gvseo-sb-' + p.label + '">' + (labels[p.label] || p.label) + '</span>');
            $row.find('.gvseo-ts-cell').text('just now');
        });
    }

    function updatePageSelect(posts) {
        var $sel = $('#gvseo-page-select');
        var current = $sel.val();
        $sel.find('option[value!=""]').each(function () {
            var id = $(this).val();
            var p = posts.find(function (x) { return String(x.id) === String(id); });
            if (p && p.score !== null) {
                $(this).text(p.title + ' (' + p.score + '/100)')
                    .attr('data-score', p.score).attr('data-label', p.label);
            }
        });
        if (current) { $sel.val(current); }
    }

    /* ── Per-Page Analyzer ─────────────────────────────────────────── */
    function initPageAnalyzer() {
        $('#gvseo-analyze-btn').on('click', function () {
            var postId = $('#gvseo-page-select').val();
            if (!postId) { alert('Please select a page.'); return; }
            runPageAnalysis(postId);
        });

        // Clicking a row in the table
        $(document).on('click', '.gvseo-open-row', function (e) {
            e.preventDefault();
            var $el = $(this);
            var id = $el.data('id');
            $('#gvseo-page-select').val(id);
            $('#gvseo-edit-link').attr('href', $el.data('edit') || '#').show();
            $('#gvseo-view-link').attr('href', $el.data('view') || '#').show();
            runPageAnalysis(id);
            $('html,body').animate({ scrollTop: $('#gvseo-page-select').offset().top - 80 }, 300);
        });

        $('#gvseo-page-select').on('change', function () {
            var $opt = $(this).find('option:selected');
            var edit = $opt.data('edit');
            var view = $opt.data('view');
            if (edit) { $('#gvseo-edit-link').attr('href', edit).show(); } else { $('#gvseo-edit-link').hide(); }
            if (view) { $('#gvseo-view-link').attr('href', view).show(); } else { $('#gvseo-view-link').hide(); }
        });
    }

    function runPageAnalysis(postId) {
        $('#gvseo-results').hide();
        $('#gvseo-empty-hint').hide();
        $('#gvseo-analyzing').show();

        $.post(RAS.ajax, { action: 'gvseo_analyze_post', nonce: RAS.nonce, post_id: postId }, function (res) {
            $('#gvseo-analyzing').hide();
            if (!res.success) { alert('Analysis failed.'); return; }
            renderResults(res.data, postId);
        }).fail(function () {
            $('#gvseo-analyzing').hide();
            alert('Connection error.');
        });
    }

    function renderResults(data, postId) {
        var score  = data.score;
        var label  = data.label;
        var checks = data.results;

        // Update the table row score live
        var $row = $('[data-id="' + postId + '"]');
        if ($row.length) {
            $row.attr('data-label', label);
        }

        // Score header
        var $circle = $('#gvseo-score-circle').removeClass('excellent good needs_work poor').addClass(label);
        $('#gvseo-score-num').text(score);
        $('#gvseo-cnt-pass').text(data.pass);
        $('#gvseo-cnt-warn').text(data.warn);
        $('#gvseo-cnt-fail').text(data.fail);

        var $fill = $('#gvseo-score-fill').removeClass('excellent good needs_work poor').addClass(label);
        $fill.css('width', score + '%');
        var $badge = $('#gvseo-score-badge').removeClass('excellent good needs_work poor').addClass(label);
        var labelText = { excellent: '★ Excellent', good: '▲ Good', needs_work: '⚠ Needs Work', poor: '✗ Poor' };
        $badge.text(labelText[label] || label);

        // Page title
        var $opt = $('#gvseo-page-select option[value="' + postId + '"]');
        $('#gvseo-result-title').text($opt.text().replace(/\s*\(.*\)$/, ''));

        // Group checks by category
        var grouped = {};
        var catOrder = ['title', 'meta', 'url', 'headings', 'content', 'images', 'links', 'technical', 'schema', 'social', 'keyword', 'product'];
        catOrder.forEach(function (cat) { grouped[cat] = []; });
        Object.keys(checks).forEach(function (id) {
            var r = checks[id];
            if (!grouped[r.cat]) { grouped[r.cat] = []; }
            grouped[r.cat].push(r);
        });

        var icons = { pass: '✓', warn: '⚠', fail: '✗' };
        var catNames = Object.assign({
            title:    'Title Tag',
            meta:     'Meta Description',
            url:      'URL / Slug',
            headings: 'Headings',
            content:  'Content',
            images:   'Image SEO',
            links:    'Links',
            technical:'Technical SEO',
            schema:   'Schema',
            social:   'Social / OG',
            keyword:  'Focus Keyword',
            product:  'WooCommerce Product',
        }, GVSEO_SEO.cats || {});
        var catIcons = Object.assign({
            title:    '🏷️',
            meta:     '📝',
            url:      '🔗',
            headings: '📑',
            content:  '📄',
            images:   '🖼️',
            links:    '🔀',
            technical:'⚙️',
            schema:   '⬡',
            social:   '📣',
            keyword:  '🎯',
            product:  '🛒',
        }, GVSEO_SEO.catIcons || {});
        // Build grouped from all categories in catOrder
        catOrder.forEach(function(cat) { if (!grouped[cat]) { grouped[cat] = []; } });
        var html = '';

        catOrder.forEach(function (cat) {
            var items = grouped[cat];
            if (!items || !items.length) { return; }
            var catPass = items.filter(function (r) { return r.status === 'pass'; }).length;
            html += '<div class="gvseo-check-cat" data-cat="' + cat + '">';
            html += '<div class="gvseo-check-cat-head"><h5>' + (catIcons[cat] || '') + ' ' + (catNames[cat] || cat) + '</h5>';
            html += '<span class="gvseo-check-cat-score">' + catPass + '/' + items.length + '</span></div>';
            items.forEach(function (r) {
                html += '<div class="gvseo-check-item ' + r.status + '">';
                html += '<span class="gvseo-ci-icon">' + icons[r.status] + '</span>';
                html += '<div class="gvseo-ci-body">';
                html += '<div class="gvseo-ci-label">' + escHtml(r.label) + '</div>';
                html += '<div class="gvseo-ci-msg">' + escHtml(r.message) + '</div>';
                if (r.fix) { html += '<div class="gvseo-ci-fix">💡 ' + escHtml(r.fix) + '</div>'; }
                html += '</div></div>';
            });
            html += '</div>';
        });

        $('#gvseo-check-grid').html(html);
        $('#gvseo-results').show();
    }

    /* ── Table Filter + Search ────────────────────────────────────── */
    function initTable() {
        var $rows   = $('#gvseo-pages-table .gvseo-tr');
        var current = 'all';
        var query   = '';

        function applyFilters() {
            $rows.each(function () {
                var $r   = $(this);
                var lbl  = $r.data('label') || 'unanalyzed';
                var txt  = $r.text().toLowerCase();
                var show = (current === 'all' || lbl === current) && (!query || txt.includes(query));
                $r.toggleClass('gvseo-hidden', !show);
            });
        }

        $('#gvseo-filters').on('click', '.gvseo-filter-btn', function () {
            current = $(this).data('filter');
            $(this).addClass('active').siblings().removeClass('active');
            applyFilters();
        });

        $('#gvseo-table-search').on('input', function () {
            query = $(this).val().toLowerCase().trim();
            applyFilters();
        });
    }

    /* ═══ META BOX ═══════════════════════════════════════════════════ */
    if ($('.gvseo-mb').length) {
        initMetaBoxTabs();
        initSchemaGroups();
        initRepeaters();
        initMetaDescCounter();
        initJsonValidator();
        initMbAnalyze();
    }

    /* ── Meta Box Tabs ───────────────────────────────────────────── */
    function initMetaBoxTabs() {
        $(document).on('click', '.gvseo-mb-tab', function () {
            var tab = $(this).data('tab');
            $('.gvseo-mb-tab').removeClass('gvseo-mb-tab-active');
            $(this).addClass('gvseo-mb-tab-active');
            $('.gvseo-mb-panel').removeClass('gvseo-mb-panel-active');
            $('#gvseo-tab-' + tab).addClass('gvseo-mb-panel-active');
        });
    }

    /* ── Schema mode → show/hide override section ─────────────────── */
    $(document).on('change', 'input[name="_gvseo_schema_mode"]', function () {
        $('#gvseo-schema-override').toggle(this.value === 'override');
    });

    /* ── Schema type → show/hide groups ──────────────────────────── */
    function initSchemaGroups() {
        function update() {
            var val = $('#gvseo_schema_type').val() || '';
            $('.gvseo-schema-group').each(function () {
                var forTypes = $(this).data('for') || '';
                $(this).toggleClass('gvseo-sg-active', forTypes.indexOf(val) !== -1);
            });
        }
        $('#gvseo_schema_type').on('change', update);
        update();
    }

    /* ── Repeater rows (FAQ + Steps) ─────────────────────────────── */
    function initRepeaters() {
        $(document).on('click', '.gvseo-add-row', function () {
            var listId = $(this).data('list');
            var tpl    = $(this).data('template');
            var $list  = $('#' + listId);
            var num    = $list.children().length + 1;
            var html;
            if (tpl === 'faq') {
                html = '<div class="gvseo-repeater-item"><div class="gvseo-ri-num">' + num + '</div><div class="gvseo-ri-body"><input type="text" name="_gvseo_faq_q[]" placeholder="Question"><textarea name="_gvseo_faq_a[]" rows="2" placeholder="Answer"></textarea></div><button type="button" class="gvseo-ri-del" data-list="' + listId + '">✕</button></div>';
            } else {
                html = '<div class="gvseo-repeater-item"><div class="gvseo-ri-num">' + num + '</div><div class="gvseo-ri-body"><input type="text" name="_gvseo_step_name[]" placeholder="Step title"><textarea name="_gvseo_step_text[]" rows="2" placeholder="Step description"></textarea></div><button type="button" class="gvseo-ri-del" data-list="' + listId + '">✕</button></div>';
            }
            $list.append(html);
            renumberList($list);
        });

        $(document).on('click', '.gvseo-ri-del', function () {
            var $list = $('#' + $(this).data('list'));
            $(this).closest('.gvseo-repeater-item').remove();
            renumberList($list);
        });
    }

    function renumberList($list) {
        $list.children().each(function (i) {
            $(this).find('.gvseo-ri-num').text(i + 1);
        });
    }

    /* ── Meta desc character counter ──────────────────────────────── */
    function initMetaDescCounter() {
        var $ta    = $('#gvseo-meta-desc');
        var $count = $('#gvseo-desc-count');
        var $fill  = $('#gvseo-desc-fill');

        function update() {
            var len = $ta.val().length;
            $count.text(len + ' / 160');
            var pct = Math.min(len / 160 * 100, 100);
            var bg  = len < 120 ? '#f59e0b' : (len <= 160 ? '#10b981' : '#ef4444');
            $fill.css({ width: pct + '%', background: bg });
        }

        $ta.on('input', update);
        update();
    }

    /* ── JSON Validator ───────────────────────────────────────────── */
    function initJsonValidator() {
        $(document).on('input', '.gvseo-code-area', function () {
            var val = $(this).val().trim();
            var $st = $('#gvseo-json-status');
            if (!val) { $st.hide().removeClass('ok err'); return; }
            try {
                JSON.parse(val);
                $st.removeClass('err').addClass('ok').text('✓ Valid JSON-LD').show();
            } catch (e) {
                $st.removeClass('ok').addClass('err').text('✗ ' + e.message).show();
            }
        });
    }

    /* ── Meta box "Analyze Now" button ───────────────────────────── */
    function initMbAnalyze() {
        $(document).on('click', '.gvseo-mb-analyze', function () {
            var $btn = $(this).prop('disabled', true).text('Analyzing…');
            var postId = $(this).data('post');

            $.post(RAS.ajax, { action: 'gvseo_analyze_post', nonce: RAS.nonce, post_id: postId }, function (res) {
                $btn.prop('disabled', false);
                if (!res.success) { $btn.text('✗ Error'); return; }
                var d = res.data;
                $btn.text('↺ Re-analyze');

                // Update score circle
                var $circle = $('.gvseo-seo-circle').removeClass('gvseo-seo-circle-excellent gvseo-seo-circle-good gvseo-seo-circle-needs_work gvseo-seo-circle-poor gvseo-seo-circle-none')
                    .addClass('gvseo-seo-circle-' + d.label);
                $circle.find('span').text(d.score);
                $('.gvseo-seo-score-meta p').text('Just analyzed');

                // Update badge in tab
                var $badge = $('.gvseo-mb-badge').removeClass('gvseo-mb-badge-excellent gvseo-mb-badge-good gvseo-mb-badge-needs_work gvseo-mb-badge-poor')
                    .addClass('gvseo-mb-badge-' + d.label).text(d.score).show();

                // Render mini checks
                var icons = { pass: '✓', warn: '⚠', fail: '✗' };
                var html = '';
                Object.keys(d.results).forEach(function (id) {
                    var r = d.results[id];
                    html += '<div class="gvseo-mc ' + r.status + '">';
                    html += '<span class="gvseo-mc-icon">' + icons[r.status] + '</span>';
                    html += '<span class="gvseo-mc-label">' + escHtml(r.label) + '</span>';
                    html += '<span class="gvseo-mc-msg">' + escHtml(r.message) + '</span>';
                    html += '</div>';
                });
                $('#gvseo-mb-checks').html(html);
            }).fail(function () {
                $btn.prop('disabled', false).text('✗ Error');
            });
        });
    }

    /* ── Utility ─────────────────────────────────────────────────── */
    function scoreColor(s) {
        if (s >= 80) { return '#10b981'; }
        if (s >= 60) { return '#3b82f6'; }
        if (s >= 40) { return '#f59e0b'; }
        return '#ef4444';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})(jQuery);

/* ── Local Business Settings JS ────────────────────────────────────────── */
(function() {
    // Toggle lb fields panel
    var lbToggle = document.getElementById('gvseo-lb-toggle');
    var lbFields = document.getElementById('gvseo-lb-fields');
    if (lbToggle && lbFields) {
        lbToggle.addEventListener('change', function() {
            lbFields.style.display = this.checked ? '' : 'none';
        });
    }

    // Day pill toggle — keep .active class in sync with checkbox
    document.querySelectorAll('.gvseo-day-pill').forEach(function(pill) {
        var cb = pill.querySelector('input[type="checkbox"]');
        if (!cb) return;
        pill.classList.toggle('active', cb.checked);
        cb.addEventListener('change', function() {
            pill.classList.toggle('active', this.checked);
        });
        pill.addEventListener('click', function(e) {
            if (e.target === cb) return; // native checkbox already handled
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change'));
            e.preventDefault();
        });
    });

    var hoursWrap = document.getElementById('gvseo-lb-hours');
    var addBtn    = document.getElementById('gvseo-add-hours');
    if (!hoursWrap || !addBtn) return;

    var allDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    // Remove row
    function wireRemove(row) {
        var btn = row.querySelector('.gvseo-remove-hours');
        if (!btn) return;
        btn.addEventListener('click', function() {
            row.parentNode.removeChild(row);
            reindex();
        });
    }

    // Reindex all rows after add/remove
    function reindex() {
        var rows = hoursWrap.querySelectorAll('.gvseo-hours-row');
        rows.forEach(function(row, i) {
            row.setAttribute('data-idx', i);
            row.querySelectorAll('[name^="lb_hour_days["]').forEach(function(cb) {
                cb.name = 'lb_hour_days[' + i + '][]';
            });
            var opens = row.querySelector('[name^="lb_hour_opens"]');
            if (opens) opens.name = 'lb_hour_opens[' + i + ']';
            var closes = row.querySelector('[name^="lb_hour_closes"]');
            if (closes) closes.name = 'lb_hour_closes[' + i + ']';
        });
    }

    // Build a new row
    function buildRow(idx) {
        var row = document.createElement('div');
        row.className = 'gvseo-hours-row';
        row.setAttribute('data-idx', idx);

        var daysDiv = document.createElement('div');
        daysDiv.className = 'gvseo-hours-days';
        allDays.forEach(function(day) {
            var lbl = document.createElement('label');
            lbl.className = 'gvseo-day-pill';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'lb_hour_days[' + idx + '][]';
            cb.value = day;
            cb.addEventListener('change', function() {
                lbl.classList.toggle('active', this.checked);
            });
            lbl.addEventListener('click', function(e) {
                if (e.target === cb) return;
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change'));
                e.preventDefault();
            });
            lbl.appendChild(cb);
            lbl.appendChild(document.createTextNode(day.slice(0, 3)));
            daysDiv.appendChild(lbl);
        });

        var timesDiv = document.createElement('div');
        timesDiv.className = 'gvseo-hours-times';

        var opens = document.createElement('input');
        opens.type = 'time'; opens.name = 'lb_hour_opens[' + idx + ']'; opens.value = '09:00';
        var closes = document.createElement('input');
        closes.type = 'time'; closes.name = 'lb_hour_closes[' + idx + ']'; closes.value = '17:00';
        var sep = document.createElement('span');
        sep.textContent = 'to';

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-remove-hours';
        removeBtn.textContent = '✕';

        timesDiv.appendChild(opens);
        timesDiv.appendChild(sep);
        timesDiv.appendChild(closes);
        timesDiv.appendChild(removeBtn);
        row.appendChild(daysDiv);
        row.appendChild(timesDiv);
        wireRemove(row);
        return row;
    }

    // Wire existing rows
    hoursWrap.querySelectorAll('.gvseo-hours-row').forEach(wireRemove);

    // Add new row
    addBtn.addEventListener('click', function() {
        var rows = hoursWrap.querySelectorAll('.gvseo-hours-row');
        hoursWrap.appendChild(buildRow(rows.length));
    });
})();

/* ── LocalBusiness Multi-location JS ─────────────────────────────────── */
(function() {
    var locsWrap  = document.getElementById('gvseo-lb-locations');
    var addLocBtn = document.getElementById('gvseo-add-location');
    var emptyMsg  = document.getElementById('gvseo-lb-empty');
    if (!locsWrap) return;

    var allDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    /* ── Day pills ─────────────────────────── */
    function wireDayPills(container) {
        container.querySelectorAll('.gvseo-day-pill').forEach(function(pill) {
            var cb = pill.querySelector('input[type="checkbox"]');
            if (!cb) return;
            pill.classList.toggle('active', cb.checked);
            cb.addEventListener('change', function() {
                pill.classList.toggle('active', this.checked);
            });
            pill.addEventListener('click', function(e) {
                if (e.target === cb) return;
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change'));
                e.preventDefault();
            });
        });
    }

    /* ── Collapse/expand card ──────────────── */
    function wireCollapse(card) {
        var btn = card.querySelector('.gvseo-lb-toggle-btn');
        if (!btn) return;
        btn.addEventListener('click', function() {
            card.classList.toggle('collapsed');
        });
    }

    /* ── Remove location ────────────────────── */
    function wireRemoveLocation(card) {
        var btn = card.querySelector('.gvseo-lb-remove');
        if (!btn) return;
        btn.addEventListener('click', function() {
            if (!confirm('Remove this location?')) return;
            card.parentNode.removeChild(card);
            reindexLocations();
            if (locsWrap.querySelectorAll('.gvseo-lb-card').length === 0 && emptyMsg) {
                emptyMsg.style.display = '';
            }
        });
    }

    /* ── Live update card title ─────────────── */
    function wireTitleSync(card) {
        var nameInput = card.querySelector('.gvseo-lb-name-input');
        var titleEl   = card.querySelector('.gvseo-lb-card-name');
        var typeSelect= card.querySelector('.gvseo-lb-type-select');
        var li        = parseInt(card.getAttribute('data-loc'), 10);
        if (nameInput && titleEl) {
            nameInput.addEventListener('input', function() {
                titleEl.textContent = this.value || 'Location ' + (li + 1);
            });
        }
    }

    /* ── Hours per location ─────────────────── */
    function wireHours(card) {
        var li      = parseInt(card.getAttribute('data-loc'), 10);
        var wrap    = card.querySelector('.gvseo-hours-wrap');
        var addBtn  = card.querySelector('.gvseo-add-hours');
        if (!wrap || !addBtn) return;

        function reindexHours() {
            wrap.querySelectorAll('.gvseo-hours-row').forEach(function(row, hi) {
                row.querySelectorAll('[name*="[hour_days]"]').forEach(function(cb) {
                    cb.name = 'lb_loc[' + li + '][hour_days][' + hi + '][]';
                });
                var opens = row.querySelector('[name*="[hour_opens]"]');
                if (opens) opens.name = 'lb_loc[' + li + '][hour_opens][' + hi + ']';
                var closes = row.querySelector('[name*="[hour_closes]"]');
                if (closes) closes.name = 'lb_loc[' + li + '][hour_closes][' + hi + ']';
            });
        }

        function buildHourRow(hi) {
            var row = document.createElement('div');
            row.className = 'gvseo-hours-row';
            var daysDiv = document.createElement('div');
            daysDiv.className = 'gvseo-hours-days';
            allDays.forEach(function(day) {
                var lbl = document.createElement('label');
                lbl.className = 'gvseo-day-pill';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'lb_loc[' + li + '][hour_days][' + hi + '][]';
                cb.value = day;
                cb.addEventListener('change', function() { lbl.classList.toggle('active', this.checked); });
                lbl.addEventListener('click', function(e) {
                    if (e.target === cb) return;
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                    e.preventDefault();
                });
                lbl.appendChild(cb);
                lbl.appendChild(document.createTextNode(day.slice(0, 3)));
                daysDiv.appendChild(lbl);
            });
            var timesDiv = document.createElement('div');
            timesDiv.className = 'gvseo-hours-times';
            var opens = document.createElement('input');
            opens.type = 'time'; opens.name = 'lb_loc[' + li + '][hour_opens][' + hi + ']'; opens.value = '09:00';
            var closes = document.createElement('input');
            closes.type = 'time'; closes.name = 'lb_loc[' + li + '][hour_closes][' + hi + ']'; closes.value = '17:00';
            var sep = document.createElement('span'); sep.textContent = 'to';
            var delBtn = document.createElement('button');
            delBtn.type = 'button'; delBtn.className = 'gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-remove-hours';
            delBtn.textContent = '✕';
            delBtn.addEventListener('click', function() { row.parentNode.removeChild(row); reindexHours(); });
            timesDiv.appendChild(opens); timesDiv.appendChild(sep);
            timesDiv.appendChild(closes); timesDiv.appendChild(delBtn);
            row.appendChild(daysDiv); row.appendChild(timesDiv);
            return row;
        }

        // Wire existing remove buttons
        wrap.querySelectorAll('.gvseo-remove-hours').forEach(function(btn) {
            btn.addEventListener('click', function() {
                btn.closest('.gvseo-hours-row').remove();
                reindexHours();
            });
        });

        addBtn.addEventListener('click', function() {
            var hi = wrap.querySelectorAll('.gvseo-hours-row').length;
            wrap.appendChild(buildHourRow(hi));
        });
    }

    /* ── Reindex all location cards ─────────── */
    function reindexLocations() {
        locsWrap.querySelectorAll('.gvseo-lb-card').forEach(function(card, li) {
            card.setAttribute('data-loc', li);
            card.querySelectorAll('[name]').forEach(function(el) {
                el.name = el.name.replace(/lb_loc\[\d+\]/, 'lb_loc[' + li + ']');
            });
            var titleEl = card.querySelector('.gvseo-lb-card-name');
            if (titleEl) {
                var nameInput = card.querySelector('.gvseo-lb-name-input');
                if (!nameInput || !nameInput.value) {
                    titleEl.textContent = 'Location ' + (li + 1);
                }
            }
        });
    }

    /* ── Build a new blank location card ────── */
    function buildLocationCard(li) {
        var card = document.createElement('div');
        card.className = 'gvseo-lb-card';
        card.setAttribute('data-loc', li);

        var lb_types = {
            'LocalBusiness': 'Local Business (generic)',
            'Restaurant': 'Restaurant', 'Dentist': 'Dentist',
            'Attorney': 'Attorney / Lawyer', 'BeautySalon': 'Beauty Salon',
            'DigitalMarketingAgency': 'Digital Marketing Agency',
            'RealEstateAgent': 'Real Estate Agent', 'Hotel': 'Hotel',
            'Physician': 'Physician / Doctor', 'Store': 'Retail Store',
            'ProfessionalService': 'Professional Service',
        };

        var typeOptions = Object.entries(lb_types).map(function(e) {
            return '<option value="' + e[0] + '">' + e[1] + '</option>';
        }).join('');

        var dayPills = allDays.map(function(d) {
            return '<label class="gvseo-day-pill"><input type="checkbox" name="lb_loc[' + li + '][hour_days][0][]" value="' + d + '">' + d.slice(0, 3) + '</label>';
        }).join('');

        card.innerHTML = '<div class="gvseo-lb-card-head">' +
            '<div class="gvseo-lb-card-title">' +
                '<button type="button" class="gvseo-lb-toggle-btn">▾</button>' +
                '<strong class="gvseo-lb-card-name">Location ' + (li + 1) + '</strong>' +
            '</div>' +
            '<div class="gvseo-lb-card-actions">' +
                '<label class="gvseo-toggle"><input type="checkbox" name="lb_loc[' + li + '][enabled]" value="1" checked><span></span></label>' +
                '<button type="button" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-lb-remove">✕ Remove</button>' +
            '</div>' +
        '</div>' +
        '<div class="gvseo-lb-card-body">' +
            '<div class="gvseo-field-row">' +
                '<div class="gvseo-field"><label>Business Type</label>' +
                    '<select name="lb_loc[' + li + '][type]" class="gvseo-cpt-select gvseo-lb-type-select">' + typeOptions + '</select></div>' +
                '<div class="gvseo-field"><label>Location / Branch Name</label>' +
                    '<input type="text" name="lb_loc[' + li + '][name]" class="gvseo-lb-name-input" placeholder="e.g. Main Office, North Branch"></div>' +
            '</div>' +
            '<div class="gvseo-field"><label>Street Address</label><input type="text" name="lb_loc[' + li + '][street]"></div>' +
            '<div class="gvseo-field-row" style="margin-top:10px;">' +
                '<div class="gvseo-field"><label>City</label><input type="text" name="lb_loc[' + li + '][city]"></div>' +
                '<div class="gvseo-field"><label>State</label><input type="text" name="lb_loc[' + li + '][state]"></div>' +
                '<div class="gvseo-field"><label>Postcode</label><input type="text" name="lb_loc[' + li + '][postcode]"></div>' +
                '<div class="gvseo-field"><label>Country</label><input type="text" name="lb_loc[' + li + '][country]" value="AU" maxlength="2"></div>' +
            '</div>' +
            '<div class="gvseo-field-row" style="margin-top:10px;">' +
                '<div class="gvseo-field"><label>Phone</label><input type="tel" name="lb_loc[' + li + '][phone]"></div>' +
                '<div class="gvseo-field"><label>Email</label><input type="email" name="lb_loc[' + li + '][email]"></div>' +
                '<div class="gvseo-field"><label>Latitude</label><input type="text" name="lb_loc[' + li + '][lat]"></div>' +
                '<div class="gvseo-field"><label>Longitude</label><input type="text" name="lb_loc[' + li + '][lng]"></div>' +
            '</div>' +
            '<h4 class="gvseo-section-h4">🕐 Opening Hours</h4>' +
            '<div class="gvseo-hours-wrap" data-loc="' + li + '">' +
                '<div class="gvseo-hours-row">' +
                    '<div class="gvseo-hours-days">' + dayPills + '</div>' +
                    '<div class="gvseo-hours-times">' +
                        '<input type="time" name="lb_loc[' + li + '][hour_opens][0]" value="09:00">' +
                        '<span>to</span>' +
                        '<input type="time" name="lb_loc[' + li + '][hour_closes][0]" value="17:00">' +
                        '<button type="button" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-remove-hours">✕</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<button type="button" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-add-hours" data-loc="' + li + '" style="margin-top:6px;">+ Add Hours Group</button>' +
            '<div class="gvseo-toggle-row" style="margin-top:16px;">' +
                '<div><strong>Inherit Social Profiles (sameAs)</strong></div>' +
                '<label class="gvseo-toggle"><input type="checkbox" name="lb_loc[' + li + '][same_as_org]" value="1" checked><span></span></label>' +
            '</div>' +
            '<input type="hidden" name="lb_loc[' + li + '][description]" value="">' +
            '<input type="hidden" name="lb_loc[' + li + '][maps_url]" value="">' +
            '<input type="hidden" name="lb_loc[' + li + '][price_range]" value="">' +
            '<input type="hidden" name="lb_loc[' + li + '][payment]" value="">' +
            '<input type="hidden" name="lb_loc[' + li + '][currencies]" value="AUD">' +
            '<input type="hidden" name="lb_loc[' + li + '][area_served]" value="">' +
            '<input type="hidden" name="lb_loc[' + li + '][lng]" value="">' +
        '</div>';

        wireCollapse(card);
        wireRemoveLocation(card);
        wireTitleSync(card);
        wireHours(card);
        wireDayPills(card);
        return card;
    }

    /* ── Init existing cards ─────────────────── */
    locsWrap.querySelectorAll('.gvseo-lb-card').forEach(function(card) {
        wireCollapse(card);
        wireRemoveLocation(card);
        wireTitleSync(card);
        wireHours(card);
        wireDayPills(card);
    });

    /* ── Add location button ─────────────────── */
    if (addLocBtn) {
        addLocBtn.addEventListener('click', function() {
            if (emptyMsg) emptyMsg.style.display = 'none';
            var li = locsWrap.querySelectorAll('.gvseo-lb-card').length;
            locsWrap.appendChild(buildLocationCard(li));
        });
    }
})();
