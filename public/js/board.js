document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-insert-daily-report]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = button.closest('form');
            var textarea = form ? form.querySelector('[name="body"]') : null;
            var daySelect = form ? form.querySelector('[data-report-day]') : null;
            if (!textarea) return;

            var dayLabel = daySelect ? daySelect.value : '明日の作業';
            var template = [
                '[1]昨日、今日の作業：',
                '',
                '[2]' + dayLabel + '：',
                '',
                '[3]特記事項：',
                '',
                '[4]写真内容：',
            ].join('\n');

            var start = textarea.selectionStart || 0;
            var end = textarea.selectionEnd || 0;
            var prefix = textarea.value.slice(0, start);
            var suffix = textarea.value.slice(end);
            var separator = prefix !== '' && !prefix.endsWith('\n') ? '\n' : '';
            textarea.value = prefix + separator + template + suffix;
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = (prefix + separator + template).length;
        });
    });

    document.querySelectorAll('[data-file-input]').forEach(function (input) {
        var form = input.closest('form');
        var summary = form ? form.querySelector('[data-file-summary]') : null;

        input.addEventListener('change', function () {
            var limit = parseInt(input.dataset.fileLimit || '5', 10);
            if (input.files.length > limit) {
                window.alert('添付ファイルは最大' + limit + '件です。');
                input.value = '';
            }

            if (summary) {
                summary.textContent = Array.from(input.files).map(function (file) {
                    return file.name;
                }).join('、');
            }
        });
    });
});
