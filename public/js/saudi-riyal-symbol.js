(function () {
    'use strict';

    var script = document.currentScript;
    var imageUrl = script && script.dataset.symbolSrc ? script.dataset.symbolSrc : '/img/saudi-riyal-new.svg';

    function start() {
        var code = document.getElementById('__code');
        var symbolField = document.getElementById('__symbol');
        if (!code || code.value.toUpperCase() !== 'SAR' || !symbolField || !symbolField.value) return;

        var symbol = symbolField.value;
        var excluded = 'script,style,textarea,select,option,code,pre,[contenteditable="true"],.sar-currency-icon';

        function replaceText(node) {
            if (!node.parentElement || node.parentElement.closest(excluded) || node.nodeValue.indexOf(symbol) === -1) return;
            var parts = node.nodeValue.split(symbol);
            var fragment = document.createDocumentFragment();
            parts.forEach(function (part, index) {
                if (index) {
                    var icon = document.createElement('img');
                    icon.className = 'sar-currency-icon';
                    icon.src = imageUrl;
                    icon.alt = 'ريال سعودي';
                    fragment.appendChild(icon);
                }
                if (part) fragment.appendChild(document.createTextNode(part));
            });
            node.replaceWith(fragment);
        }

        function scan(root) {
            if (root.nodeType === Node.TEXT_NODE) {
                replaceText(root);
                return;
            }
            if (root.nodeType !== Node.ELEMENT_NODE || root.matches(excluded)) return;
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            var nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);
            nodes.forEach(replaceText);
        }

        scan(document.body);
        var pending = [];
        var scheduled = false;
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'characterData') pending.push(mutation.target);
                else mutation.addedNodes.forEach(function (node) { pending.push(node); });
            });
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(function () {
                var nodes = pending;
                pending = [];
                scheduled = false;
                nodes.forEach(scan);
            });
        });
        observer.observe(document.body, {subtree: true, childList: true, characterData: true});
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
