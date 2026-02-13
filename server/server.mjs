/*
 * Simple screenshot server using puppeteer-real-browser and AutoConsent
 *
 * Usage:
 *   node server.mjs
 *
 * Example request:
 *   http://localhost:8080/?url=https://example.com
 *
 * Alpine Linux setup example:
 * apk add npm xvfb chromium
 * npm install puppeteer-real-browser @duckduckgo/autoconsent
 * 
 * Crontab:
 * @reboot /usr/bin/node /root/server.mjs >&- 2>&-
 */

import { connect } from 'puppeteer-real-browser';
import autoconsent from '@duckduckgo/autoconsent/extra';
import http from 'http';
import { URL } from 'url';
import fs from 'fs';

const PORT = process.env.PORT ? Number(process.env.PORT) : 8080;
const TIMEOUT = process.env.TIMEOUT ? Number(process.env.TIMEOUT) : 55000;

// Load cookie banner and accept button selectors from external files
const cookieBannerElements = fs.readFileSync(new URL('./cookie-banners.txt', import.meta.url), 'utf-8')
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0);

const cookieAcceptButtonSelectors = fs.readFileSync(new URL('./cookie-accept-buttons.txt', import.meta.url), 'utf-8')
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0);

async function takeScreenshot(targetUrl) {
    const { page } = await connect({
        headless: 'auto',
        fingerprint: true,
        turnstile: true,
        tf: true,
    });

    // AutoConsent injizieren
    await page.evaluateOnNewDocument(autoconsent.script);

    await page.setViewport({ width: 1920, height: 1080 });
    await page.goto(targetUrl, { waitUntil: 'networkidle2', timeout: TIMEOUT });

    // short wait to allow any dynamic content to load
    try {
        await page.waitForFunction(() => true, { timeout: 1500 });
    } catch (e) {
        // ignore short wait failures
    }

    // attempt to accept cookie banners
    await acceptCookieBanner(page);

    // hide scrollbars so they don't appear in the screenshot
    try {
        await page.addStyleTag({ content: `
            ::-webkit-scrollbar { display: none !important; }
            html, body { scrollbar-width: none !important; -ms-overflow-style: none !important; }
        ` });
    } catch (e) {
        // fallback: inject via evaluate
        await page.evaluate(() => {
            const s = document.createElement('style');
            s.textContent = `::-webkit-scrollbar{display:none!important} html,body{scrollbar-width:none!important;-ms-overflow-style:none!important}`;
            document.head && document.head.appendChild(s);
        });
    }

    const buffer = await page.screenshot({ type: 'png', fullPage: false });
    await page.close();
    return buffer;
}

async function acceptCookieBanner(page) {
    await page.evaluate((bannerSelectors, acceptSelectors) => {
        // Remove cookie banners
        bannerSelectors.forEach(selector => {
            const el = document.querySelector(selector);
            if (el) el.remove(); // alternative: el.style.display = 'none';
        });

        // Attempt to click "Accept" buttons if removal didn't work
        for (const selector of acceptSelectors) {
            const button = document.querySelector(selector);
            if (button) {
                button.click();
                break;
            }
        }
    }, cookieBannerElements, cookieAcceptButtonSelectors);
}

const server = http.createServer(async (req, res) => {
    try {
        const reqUrl = new URL(req.url, `http://localhost:${PORT}`);
        if (req.method !== 'GET') {
            res.writeHead(405, { 'Content-Type': 'text/plain' });
            res.end('Method Not Allowed');
            return;
        }

        const target = reqUrl.searchParams.get('url');
        if (!target) {
            res.writeHead(400, { 'Content-Type': 'text/plain' });
            res.end('Missing `url` query parameter');
            return;
        }

        let normalizedUrl;
        try {
            normalizedUrl = new URL(target).toString();
        } catch {
            try {
                normalizedUrl = new URL('https://' + target).toString();
            } catch (err) {
                res.writeHead(400, { 'Content-Type': 'text/plain' });
                res.end('Invalid URL');
                return;
            }
        }

        const buffer = await takeScreenshot(normalizedUrl);
        res.writeHead(200, {
            'Content-Type': 'image/png',
            'Content-Length': buffer.length,
            'Cache-Control': 'no-store'
        });
        res.end(buffer);
    } catch (err) {
        console.error('Screenshot error:', err);
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Internal Server Error');
    }
});

server.listen(PORT, () => {
    console.log(`Screenshot server listening on http://localhost:${PORT}/?url=...`);
});