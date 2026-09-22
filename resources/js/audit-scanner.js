import jsQR from 'jsqr';

// Exposed as a plain global rather than imported as a module, because the
// scanner it feeds lives inline in a Blade view's x-data block (see
// resources/views/filament/assets/pages/view-asset-audit-session.blade.php)
// — pulled in as its own Vite entry, loaded only on that one page, so
// every other page's bundle stays free of this dependency.
//
// The native BarcodeDetector API this page prefers when available isn't
// implemented by Safari (desktop or iOS) at all, so jsQR is the fallback
// that makes live scanning work there too — it decodes QR codes in plain
// JS from a video frame drawn onto a canvas, needing nothing but
// getUserMedia and Canvas2D, which every browser has.
window.jsQR = jsQR;
