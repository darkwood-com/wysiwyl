// ==UserScript==
// @name         Social Like
// @namespace    https://darkwood.fr
// @version      v0.0.1
// @description  What you see is what you like
// @author       matyo91
// @match        https://*.twitter.com/*
// @license      MIT
// ==/UserScript==

// autolike what you read

(function() {
    'use strict';

    var functions = {
        likeClick() {
            document.querySelectorAll('[role="button"][data-testid="like"]').forEach((e) => {e.click()})
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
