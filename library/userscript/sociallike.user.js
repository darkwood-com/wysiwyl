// ==UserScript==
// @name         Social Like
// @namespace    https://darkwood.fr
// @version      v0.0.1
// @description  What you see is what you like
// @author       matyo91
// @match        https://twitter.com/*
// @match        https://odysee.com/*
// @match        https://www.youtube.com/*
// @match        https://github.com/*
// @match        https://www.esa.int/*
// @match        https://www.linkedin.com/*
// @license      MIT
// ==/UserScript==

"use strict";

// autolike what you read

((readStrategy, timeout = 1000) => {
    var readStrategies = {
        'greedy': (readSelector) => {
            return readSelector
        },
        'random': (readSelector) => {
            return Math.random() >= 0.5 ? readSelector : null
        }
    }

    var functions = {
        likeClick(likeSelector) {
            likeSelector.forEach((e) => {e.click()})
        },
        likeSelector(readSelector) {
            return [...readSelector].map(element => element.querySelectorAll([
                '[role="button"][data-testid="like"]', // twitter
                '.file-page__video button.button-like:not(.button--fire)', // odysee
                '#below #segmented-like-button button[aria-pressed="false"]', // youtube
                '.js-social-container:not(.on) .unstarred .js-social-form .btn.btn-sm:not(.btn-primary)', // github
                '.share button:not(.active)', // esa
                '.feed-shared-social-actions button[aria-pressed="false"]', // linkedin
            ].join(', '))).flat().reduce((acc, nodeList) => acc.concat([...nodeList]), [])
        },
        readSelector() {
            return document.querySelectorAll([
                'article', // twitter
                '.file-page__video', // odysee
                '#primary-inner', // youtube
                '.js-repo-pjax-container', // github
                '.article', // esa
                '.feed-shared-update-v2__description-wrapper', // linkedin
            ].join(', '))
        },
        like() {
            let readSelector = functions.readSelector();
            readSelector = readStrategies[readStrategy](readSelector);
            if(readSelector) {
                functions.likeClick(functions.likeSelector(readSelector));
            }

            setTimeout(() => {
                functions.like()
            }, timeout)
        },
    }

    functions.like()
})('greedy');
