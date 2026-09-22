{{--
    Injected via PanelsRenderHook::SIMPLE_LAYOUT_START (see each Panel
    Provider's ->renderHook() call), landing as the first child inside
    .fi-simple-layout — a sibling that comes right before .fi-simple-
    main-ctn (the login form's own container), so with that parent
    flipped to a row below md, this becomes the left panel and the form
    the right one, in DOM order (works unmodified in Bureau's RTL panel
    too, since flex direction follows the document's own dir).

    Hidden below md: a login form is what actually matters on a phone,
    not a decorative panel pushing it below the fold.
--}}
<style>
    @media (min-width: 768px) {
        .fi-simple-layout {
            flex-direction: row;
            align-items: stretch;
        }
    }

    .oceancy-login-panel {
        display: none;
    }

    @media (min-width: 768px) {
        .oceancy-login-panel {
            display: flex;
            flex: 0 0 38%;
            max-width: 30rem;
            min-height: 100dvh;
            position: relative;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            padding: 3rem;
            overflow: hidden;
            background: linear-gradient(165deg, #0a3438 0%, #0e7a82 55%, #1f8f6b 100%);
            text-align: center;
        }

        .oceancy-logo-glow-wrap {
            position: relative;
            display: inline-flex;
            z-index: 1;
        }

        .oceancy-logo-glow-wrap::before {
            content: '';
            position: absolute;
            inset: -1.25rem;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0) 70%);
            opacity: 0.6;
        }

        @media (prefers-reduced-motion: no-preference) {
            .oceancy-logo-glow-wrap::before {
                animation: oceancy-glow-pulse 3s ease-in-out infinite;
            }
        }

        .oceancy-login-panel img {
            width: 6rem;
            height: 6rem;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 4px 16px rgba(0, 0, 0, 0.25));
        }

        .oceancy-login-panel h1 {
            position: relative;
            z-index: 1;
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .oceancy-login-panel p {
            position: relative;
            z-index: 1;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9375rem;
            max-width: 20rem;
            margin: 0;
            line-height: 1.5;
        }

        .oceancy-login-panel svg {
            position: absolute;
            inset: auto 0 0 0;
            width: 100%;
            height: auto;
            opacity: 0.12;
        }
    }

    /*
     * The sign-in card itself — restyled to match the Login page class
     * (App\Filament\Auth\Pages\Login), which is what actually adds the
     * field icons and rearranges Remember me / Forgot password; this
     * only styles what that produces.
     */
    @media (prefers-reduced-motion: no-preference) {
        .fi-simple-main {
            animation: oceancy-fade-up 0.5s ease-out;
        }
    }

    .oceancy-remember-row {
        align-items: center;
    }

    .oceancy-signin-btn {
        background: linear-gradient(135deg, #0e7a82 0%, #1f8f6b 100%) !important;
        background-size: 150% 150% !important;
        background-position: 0% 50%;
        border: none !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background-position 0.3s ease;
    }

    .oceancy-signin-btn:hover {
        background-position: 100% 50%;
        transform: translateY(-1px);
        box-shadow: 0 8px 20px -6px rgba(14, 122, 130, 0.5);
    }

    /* Stacks the card and the footer line below it, both centered —
       .fi-simple-main-ctn already centers a single child both ways
       (Tailwind's items-center + justify-center), so flipping it to a
       column here just re-purposes those same two rules for the
       second, generated "child" below instead of adding new ones. */
    .fi-simple-main-ctn {
        flex-direction: column;
    }

    .fi-simple-main-ctn::after {
        content: "© {{ now()->year }} Oceancy. All rights reserved.";
        display: block;
        margin-top: 1.5rem;
        font-size: 0.75rem;
        color: rgb(156 163 175 / 0.7);
    }

    @keyframes oceancy-fade-up {
        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes oceancy-glow-pulse {
        0%, 100% {
            opacity: 0.5;
            transform: scale(0.92);
        }

        50% {
            opacity: 1;
            transform: scale(1.05);
        }
    }
</style>

<div class="oceancy-login-panel">
    <span class="oceancy-logo-glow-wrap">
        <img src="{{ asset('images/oceancy-logo-brand.png') }}" alt="" aria-hidden="true" />
    </span>
    <h1>Oceancy</h1>
    <p>One platform for every council operation — documents, meetings, inventory, and assets, all in one place.</p>

    {{-- A faint wave along the bottom edge, echoing the logo's own wave motif. --}}
    <svg viewBox="0 0 400 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M0,40 C60,10 140,70 200,40 C260,10 340,70 400,40 L400,80 L0,80 Z"
            fill="#ffffff"
        />
    </svg>
</div>
