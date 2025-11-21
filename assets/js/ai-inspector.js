(function($){
    'use strict';

    const config = window.vardiAiInspector || {};
    if (!config.nonce || !config.ajaxUrl) return;

    function formatMessage(msg){
        if (!msg) return 'پاسخ نامشخص از سرور دریافت شد.';
        if (typeof msg === 'string') return msg;
        if (msg.message) return msg.message;
        if (msg.error) return msg.error;
        if (msg.responseJSON && msg.responseJSON.data) return formatMessage(msg.responseJSON.data);
        if (msg.data && msg.data.message) return msg.data.message;
        if (typeof msg === 'object') return JSON.stringify(msg, null, 2);
        return String(msg);
    }

    function createModal(){
        if ($('.vardi-ai-overlay').length) return $('.vardi-ai-overlay');
        const overlay = $('<div class="vardi-ai-overlay" style="display:none;"></div>');
        const modal = $('<div class="vardi-ai-modal"></div>');
        const header = $('<div class="vardi-ai-header"><strong>'+ (config.title || 'تحلیل صفحه') +'</strong><span class="vardi-ai-close">×</span></div>');
        const body = $('<div class="vardi-ai-body"><p>برای تحلیل روی دکمه زیر کلیک کنید.</p></div>');
        const footer = $('<div class="vardi-ai-footer"></div>');
        const button = $('<button class="button button-primary">شروع تحلیل</button>');
        const progress = $('<div class="vardi-ai-progress" style="display:none;"><div class="vardi-ai-loading"></div><span>در حال تحلیل صفحه...</span></div>');
        footer.append(button).append(progress);
        modal.append(header).append(body).append(footer);
        overlay.append(modal);
        $('body').append(overlay);

        header.find('.vardi-ai-close').on('click', function(){ overlay.hide(); });
        overlay.on('click', function(e){ if (e.target === overlay[0]) { overlay.hide(); } });

        button.on('click', function(){
            progress.show();
            button.prop('disabled', true);
            const html = document.documentElement.outerHTML || '';
            const payload = {
                action: 'vardi_ai_analyze_page',
                nonce: config.nonce,
                url: window.location.href,
                html: html.substring(0, 60000)
            };
            $.post(config.ajaxUrl, payload, function(response){
                progress.hide();
                button.prop('disabled', false);
                if (response.success) {
                    renderAnalysis(body, response.data || {});
                } else {
                    body.html('<div class="notice notice-error"><p>' + formatMessage(response.data || 'خطا در دریافت پاسخ از AI') + '</p></div>');
                }
            }).fail(function(xhr){
                progress.hide();
                button.prop('disabled', false);
                body.html('<div class="notice notice-error"><p>' + formatMessage(xhr) + '</p></div>');
            });
        });
        return overlay;
    }

    function buildList(title, items, icon){
        if (!items || !items.length) return '';
        var html = '<div class="vardi-ai-section"><h4>' + title + '</h4><ul style="margin:0 0 6px 0; padding-right:18px; list-style:disc;">';
        items.forEach(function(item){ html += '<li>' + (icon ? icon + ' ' : '') + item + '</li>'; });
        html += '</ul></div>';
        return html;
    }

    function buildTable(title, rows){
        if (!rows || !rows.length) return '';
        var html = '<div class="vardi-ai-section"><h4>' + title + '</h4><table class="vardi-ai-table"><tbody>';
        rows.forEach(function(row){
            html += '<tr><td>' + (row.label || '') + '</td><td>' + (row.value || '') + '</td></tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function buildCompetitorChart(list){
        if (!list || !list.length) return '';
        var html = '<div class="vardi-ai-section"><h4>رقبا و فرصت‌ها</h4>';
        list.forEach(function(item){
            var gap = Math.min(100, Math.max(0, parseInt(item.gap || 0, 10) || 60));
            var color = gap >= 70 ? '#2ecc71' : gap >= 40 ? '#f1c40f' : '#e74c3c';
            html += '<div class="vardi-ai-chart-competitor"><div style="min-width:120px;">' + (item.keyword || '') + '</div><div class="bar" style="width:' + gap + '%;background:linear-gradient(90deg,'+color+',#3498db);"></div><div style="font-size:12px;">' + (item.action || '') + '</div></div>';
        });
        html += '</div>';
        return html;
    }

    function renderAnalysis(body, data){
        if (data.structured) {
            var s = data.structured;
            var score = parseInt(s.score || 0, 10);
            var safeScore = Math.min(100, Math.max(0, score));
            var scoreColor = safeScore >= 80 ? '#27ae60' : safeScore >= 50 ? '#f1c40f' : '#e74c3c';
            var scoreHtml = '<div class="vardi-ai-score-card"><div style="display:flex;justify-content:space-between;align-items:center;"><strong>امتیاز سئو</strong><span style="color:'+scoreColor+'">' + safeScore + '/100</span></div><div class="vardi-ai-score-bar"><span style="width:' + safeScore + '%;background:linear-gradient(90deg,'+scoreColor+',#6dd5ed);"></span></div><small>' + (s.summary || '') + '</small></div>';

            var keywordBadges = '';
            if (s.keywords && (s.keywords.focus || s.keywords.secondary)) {
                keywordBadges += '<div class="vardi-ai-section"><h4>کلمات کلیدی</h4><div class="vardi-ai-badges">';
                (s.keywords.focus || []).forEach(function(k){ keywordBadges += '<span class="vardi-ai-pill">کلمه اصلی: ' + k + '</span>'; });
                (s.keywords.secondary || []).forEach(function(k){ keywordBadges += '<span class="vardi-ai-pill">کلمه فرعی: ' + k + '</span>'; });
                keywordBadges += '</div></div>';
            }

            var rankMathRows = [];
            if (s.rank_math) {
                rankMathRows.push({label:'فعال بودن Rank Math', value: s.rank_math.active ? 'بله' : 'خیر'});
                if (s.rank_math.focus) rankMathRows.push({label:'کلمه کلیدی اصلی', value:s.rank_math.focus});
                if (s.rank_math.secondary && s.rank_math.secondary.length) rankMathRows.push({label:'کلمات کلیدی فرعی', value:s.rank_math.secondary.join('، ')});
                if (s.rank_math.score) rankMathRows.push({label:'امتیاز Rank Math', value:s.rank_math.score});
            }

            var overview = buildTable('مرور کلی صفحه', [
                {label:'آدرس صفحه', value: window.location.href},
                {label:'مدل AI', value: config.model || 'GapGPT'},
                {label:'تمرکز', value: (s.rank_math && s.rank_math.focus) ? s.rank_math.focus : '—'},
            ]);

            var insightsMatrix = '<div class="vardi-ai-section"><h4>خلاصه تحلیلی</h4><table class="vardi-ai-table"><tbody>' +
                '<tr><td>تعداد قوت‌ها</td><td><span class="vardi-ai-pill" style="background:#e7f9ef;color:#1e8545;">' + (s.strengths ? s.strengths.length : 0) + '</span></td></tr>' +
                '<tr><td>تعداد خطاها</td><td><span class="vardi-ai-pill" style="background:#fff3e0;color:#e67e22;">' + (s.issues ? s.issues.length : 0) + '</span></td></tr>' +
                '<tr><td>پیشنهادهای حیاتی</td><td><span class="vardi-ai-pill" style="background:#e8f4ff;color:#0d6efd;">' + (s.suggestions ? s.suggestions.length : 0) + '</span></td></tr>' +
                '</tbody></table></div>';

            var grid = '<div class="vardi-ai-grid">' +
                '<div>' + scoreHtml + keywordBadges + buildList('نقاط قوت', s.strengths || [], '✅') + buildList('خطاها و مشکلات', s.issues || [], '⚠️') + '</div>' +
                '<div>' + overview + insightsMatrix + buildList('پیشنهادهای بهبود', s.suggestions || [], '💡') + buildTable('جزئیات Rank Math', rankMathRows) + buildCompetitorChart(s.competitors) + '</div>' +
                '</div>';
            body.html(grid);
            return;
        }

        if (data.raw) {
            body.html('<div class="vardi-ai-section"><h4>گزارش AI</h4><div style="white-space:pre-wrap;">' + data.raw + '</div></div>');
        } else {
            body.html('<div class="notice notice-error"><p>پاسخی از AI دریافت نشد.</p></div>');
        }
    }

    $(document).on('click', '#wp-admin-bar-vardi-ai-inspect > a, .vardi-ai-inspect-toggle > a', function(e){
        e.preventDefault();
        const modal = createModal();
        modal.show();
    });
})(jQuery);
