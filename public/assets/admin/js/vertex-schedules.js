(function () {
    'use strict';

    var CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var scheduleModal = null;
    var deleteModal = null;
    var schedulesData = {};

    function init() {
        scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        deleteModal = new bootstrap.Modal(document.getElementById('deleteScheduleModal'));
        animateCounters();
    }

    function animateCounters() {
        document.querySelectorAll('[data-count]').forEach(function (el) {
            var target = parseInt(el.dataset.count, 10) || 0;
            if (target === 0) { el.textContent = '0'; return; }
            var start = 0;
            var duration = 800;
            var startTime = null;

            function step(ts) {
                if (!startTime) startTime = ts;
                var progress = Math.min((ts - startTime) / duration, 1);
                el.textContent = Math.floor(progress * target).toLocaleString('tr-TR');
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target.toLocaleString('tr-TR');
            }
            requestAnimationFrame(step);
        });
    }

    function jsonFetch(url, method, body) {
        var opts = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            }
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok && data.errors) {
                    var firstError = Object.values(data.errors)[0];
                    data.message = Array.isArray(firstError) ? firstError[0] : firstError;
                }
                return data;
            });
        });
    }

    // ── Prompt change → render variable inputs ──

    window.onPromptChange = function () {
        var sel = document.getElementById('schedulePromptId');
        var opt = sel.options[sel.selectedIndex];
        var container = document.getElementById('variablesContainer');
        container.innerHTML = '';
        container.classList.add('d-none');

        if (!opt || !opt.value) return;

        var promptText = opt.dataset.prompt || '';
        var randomOptions = {};
        try { randomOptions = JSON.parse(opt.dataset.randomOptions || '{}'); } catch (e) {}

        var vars = parseVariables(promptText);
        if (vars.length === 0) return;

        container.classList.remove('d-none');
        var html = '<label class="form-label">Değişkenler</label><div class="row g-2">';

        vars.forEach(function (varName) {
            var hasRandom = randomOptions[varName] && randomOptions[varName].length > 0;
            var label = humanizeVarName(varName);

            if (hasRandom) {
                html += '<div class="col-md-6"><label class="form-label form-label-sm">' + escHtml(label) + '</label>';
                html += '<select class="form-select form-select-sm variable-input" data-var="' + escHtml(varName) + '">';
                html += '<option value="__random__" selected>🎲 Rastgele</option>';
                randomOptions[varName].forEach(function (o) {
                    html += '<option value="' + escHtml(o) + '">' + escHtml(o) + '</option>';
                });
                html += '</select></div>';
            } else {
                html += '<div class="col-md-6"><label class="form-label form-label-sm">' + escHtml(label) + '</label>';
                html += '<input type="text" class="form-control form-control-sm variable-input" data-var="' + escHtml(varName) + '" placeholder="' + escHtml(label) + '"></div>';
            }
        });

        html += '</div>';
        container.innerHTML = html;
    };

    function parseVariables(text) {
        var re = /\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/g;
        var match;
        var seen = {};
        var result = [];
        while ((match = re.exec(text)) !== null) {
            if (!seen[match[1]]) {
                seen[match[1]] = true;
                result.push(match[1]);
            }
        }
        return result;
    }

    function humanizeVarName(name) {
        return name.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function collectVariables() {
        var vars = {};
        document.querySelectorAll('.variable-input').forEach(function (el) {
            vars[el.dataset.var] = el.value;
        });
        return Object.keys(vars).length > 0 ? vars : null;
    }

    // ── Frequency change ──

    window.onFrequencyChange = function () {
        var freq = document.getElementById('scheduleFrequency').value;
        document.getElementById('weeklyDaysContainer').classList.toggle('d-none', freq !== 'weekly');
        document.getElementById('monthlyDayContainer').classList.toggle('d-none', freq !== 'monthly');
    };

    // ── Open create modal ──

    window.openScheduleModal = function () {
        document.getElementById('scheduleId').value = '';
        document.getElementById('scheduleModalTitle').textContent = 'Yeni Zamanlama';
        document.getElementById('scheduleName').value = '';
        document.getElementById('schedulePromptId').value = '';
        document.getElementById('scheduleCount').value = '3';
        document.getElementById('scheduleAspectRatio').value = '';
        document.getElementById('scheduleFrequency').value = 'daily';
        document.getElementById('scheduleTime').value = '09:00';
        document.getElementById('scheduleDayOfMonth').value = '1';
        document.querySelectorAll('.day-checkbox').forEach(function (cb) { cb.checked = false; });
        document.getElementById('variablesContainer').innerHTML = '';
        document.getElementById('variablesContainer').classList.add('d-none');
        onFrequencyChange();
        scheduleModal.show();
    };

    // ── Open edit modal ──

    window.openEditModal = function (id) {
        var row = document.getElementById('schedule-row-' + id);
        if (!row) return;

        var data = schedulesData[id];
        if (!data) {
            showToast('Zamanlama verisi bulunamadı.', 'error');
            return;
        }

        document.getElementById('scheduleId').value = data.id;
        document.getElementById('scheduleModalTitle').textContent = 'Zamanlama Düzenle';
        document.getElementById('scheduleName').value = data.name;
        document.getElementById('schedulePromptId').value = data.prompt_id;
        document.getElementById('scheduleCount').value = data.count;
        document.getElementById('scheduleAspectRatio').value = data.aspect_ratio_override || '';
        document.getElementById('scheduleFrequency').value = data.frequency;
        document.getElementById('scheduleTime').value = data.time;
        document.getElementById('scheduleDayOfMonth').value = data.day_of_month || 1;

        document.querySelectorAll('.day-checkbox').forEach(function (cb) {
            cb.checked = data.days_of_week && data.days_of_week.indexOf(parseInt(cb.value, 10)) !== -1;
        });

        onFrequencyChange();
        onPromptChange();

        setTimeout(function () {
            if (data.variables) {
                Object.keys(data.variables).forEach(function (varName) {
                    var el = document.querySelector('.variable-input[data-var="' + varName + '"]');
                    if (el) el.value = data.variables[varName];
                });
            }
        }, 100);

        scheduleModal.show();
    };

    // ── Submit (create or update) ──

    window.submitSchedule = function () {
        var id = document.getElementById('scheduleId').value;
        var isEdit = id !== '';

        var daysOfWeek = [];
        document.querySelectorAll('.day-checkbox:checked').forEach(function (cb) {
            daysOfWeek.push(parseInt(cb.value, 10));
        });

        var payload = {
            name: document.getElementById('scheduleName').value.trim(),
            prompt_id: parseInt(document.getElementById('schedulePromptId').value, 10) || null,
            count: parseInt(document.getElementById('scheduleCount').value, 10) || 1,
            aspect_ratio_override: document.getElementById('scheduleAspectRatio').value || null,
            frequency: document.getElementById('scheduleFrequency').value,
            time: document.getElementById('scheduleTime').value,
            days_of_week: daysOfWeek.length > 0 ? daysOfWeek : null,
            day_of_month: parseInt(document.getElementById('scheduleDayOfMonth').value, 10) || null,
            variables: collectVariables()
        };

        if (!payload.name) { showToast('Zamanlama adı zorunlu.', 'error'); return; }
        if (!payload.prompt_id) { showToast('Prompt şablonu seçin.', 'error'); return; }

        var btn = document.getElementById('scheduleSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...';

        var url = isEdit
            ? '/admin/vertex/schedules/' + id
            : '/admin/vertex/schedules';
        var method = isEdit ? 'PUT' : 'POST';

        jsonFetch(url, method, payload)
            .then(function (res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Kaydet';

                if (res.success) {
                    scheduleModal.hide();
                    showToast(res.message, 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showToast(res.message || 'Hata oluştu.', 'error');
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Kaydet';
                showToast('Bağlantı hatası.', 'error');
            });
    };

    // ── Toggle active/inactive ──

    window.toggleSchedule = function (id) {
        jsonFetch('/admin/vertex/schedules/' + id + '/toggle', 'POST')
            .then(function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(function () { location.reload(); }, 600);
                } else {
                    showToast(res.message || 'Hata.', 'error');
                }
            })
            .catch(function () { showToast('Bağlantı hatası.', 'error'); });
    };

    // ── Run now ──

    window.runNow = function (id) {
        var doRun = function () {
            jsonFetch('/admin/vertex/schedules/' + id + '/run-now', 'POST')
                .then(function (res) {
                    if (res.success) {
                        showToast(res.message, 'success');
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        showToast(res.message || 'Hata.', 'error');
                    }
                })
                .catch(function () { showToast('Bağlantı hatası.', 'error'); });
        };

        if (typeof AdminModal !== 'undefined' && AdminModal.confirm) {
            AdminModal.confirm({
                title: 'Şimdi Çalıştır',
                message: 'Bu zamanlama hemen çalıştırılacak ve görseller kuyruğa alınacak. Devam edilsin mi?',
                confirmText: 'Çalıştır',
                onConfirm: doRun
            });
        } else {
            doRun();
        }
    };

    // ── Delete ──

    window.openDeleteModal = function (id, name) {
        document.getElementById('deleteScheduleId').value = id;
        document.getElementById('deleteScheduleName').textContent = name;
        deleteModal.show();
    };

    window.confirmDelete = function () {
        var id = document.getElementById('deleteScheduleId').value;

        jsonFetch('/admin/vertex/schedules/' + id, 'DELETE')
            .then(function (res) {
                deleteModal.hide();
                if (res.success) {
                    showToast(res.message, 'success');
                    var row = document.getElementById('schedule-row-' + id);
                    if (row) row.remove();
                } else {
                    showToast(res.message || 'Hata.', 'error');
                }
            })
            .catch(function () {
                deleteModal.hide();
                showToast('Bağlantı hatası.', 'error');
            });
    };

    // ── DOM Ready ──

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (window._vtxSchedData) schedulesData = window._vtxSchedData;
            init();
        });
    } else {
        if (window._vtxSchedData) schedulesData = window._vtxSchedData;
        init();
    }
})();
