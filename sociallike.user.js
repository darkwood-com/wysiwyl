// ==UserScript==
// @name         Social Like
// @namespace    https://darkwood.fr
// @version      v0.0.1
// @description  What you see is what you like
// @author       matyo91
// @match        https://*.twitter.com/*
// @match        https://odysee.com/*
// @match        https://www.youtube.com/*
// @license      MIT
// ==/UserScript==

// autolike what you read

(function() {
    'use strict';

    var functions = {
        likeClick() {
            document.querySelectorAll([
                '[role="button"][data-testid="like"]', // twitter
                '.section.file-page__video button.button-like:not(.button--fire)', // odysee
                '#below #segmented-like-button button[aria-pressed="false"]', // youtube
            ].join(', ')).forEach((e) => {e.click()})
        },
        like() {
            functions.likeClick()

            setTimeout(() => {
                functions.like()
            }, 1000)
        },
    }

    functions.like()
})();
