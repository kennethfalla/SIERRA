(function () {
    'use strict';
    var form = document.getElementById('systemSearchForm');
    if (!form) return;
    var input = document.getElementById('systemSearchInput');
    var list = document.getElementById('searchSuggestions');
    var status = document.getElementById('searchSuggestionStatus');
    var timer, controller, sequence = 0, active = -1, options = [];
    function close() {
        list.hidden = true; input.setAttribute('aria-expanded','false');
        input.removeAttribute('aria-activedescendant'); active = -1;
    }
    function choose(index) {
        options.forEach(function(option,i) { option.setAttribute('aria-selected',String(i === index)); });
        active = index;
        if (options[index]) { input.setAttribute('aria-activedescendant',options[index].id); options[index].scrollIntoView({block:'nearest'}); }
    }
    function render(data) {
        list.replaceChildren(); options = [];
        (data.results || []).forEach(function(result,i) {
            var option = document.createElement('div');
            option.className = 'search-suggestion'; option.id = 'search-option-'+i;
            option.setAttribute('role','option'); option.setAttribute('aria-selected','false');
            option.dataset.url = result.url;
            var title = document.createElement('strong'); title.textContent = result.title;
            var detail = document.createElement('small'); detail.textContent = result.type.charAt(0).toUpperCase()+result.type.slice(1)+(result.location ? ' · '+result.location : '');
            option.append(title,detail);
            option.addEventListener('pointerdown',function(event) { event.preventDefault(); window.location.assign(result.url); });
            list.appendChild(option); options.push(option);
        });
        if (!options.length) { close(); status.textContent = 'No suggestions yet. Press Search to see all results.'; return; }
        list.hidden = false; input.setAttribute('aria-expanded','true');
        status.textContent = options.length+' suggested results. Use the arrow keys to choose.';
    }
    input.addEventListener('input',function() {
        clearTimeout(timer); if (controller) controller.abort();
        var request = ++sequence; close(); status.textContent = '';
        if (input.value.trim().length < 2) return;
        timer = setTimeout(async function() {
            var requestController = new AbortController();
            controller = requestController;
            var timeout = setTimeout(function() { requestController.abort(); },8000);
            var url = new URL(form.action,location.href);
            url.search = new URLSearchParams(new FormData(form)).toString(); url.searchParams.set('format','json');
            status.textContent = 'Searching…';
            try {
                var response = await fetch(url.href,{signal:requestController.signal,headers:{Accept:'application/json'}});
                if (response.status === 401) throw new Error('Please sign in again.');
                if (!response.ok) throw new Error('Suggestions are unavailable. You can still press Search.');
                var data = await response.json();
                if (request === sequence && document.activeElement === input) render(data);
            } catch (error) {
                if (request === sequence) {
                    close();
                    status.textContent = error.name === 'AbortError' ? 'Suggestions took too long. Press Search to try again.' : error.message === 'Please sign in again.' ? error.message : 'Suggestions are unavailable. You can still press Search.';
                }
            } finally { clearTimeout(timeout); }
        },300);
    });
    input.addEventListener('keydown',function(event) {
        if (event.key === 'Escape') { ++sequence; clearTimeout(timer); if (controller) controller.abort(); close(); status.textContent=''; }
        if (list.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            choose(active < 0 ? (event.key === 'ArrowDown' ? 0 : options.length-1) : (active+(event.key === 'ArrowDown'?1:-1)+options.length)%options.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault(); location.assign(options[active].dataset.url);
        }
    });
    document.addEventListener('click',function(event) { if (!form.contains(event.target)) close(); });
    input.addEventListener('blur',close);
    form.addEventListener('submit',function() { ++sequence; if (controller) controller.abort(); clearTimeout(timer); close(); });
}());
