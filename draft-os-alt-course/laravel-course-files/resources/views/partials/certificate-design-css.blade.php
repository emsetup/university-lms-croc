{{-- Общие стили «бумаги» сертификата (КРОК: бирюза / изумруд + акцент сургуча) --}}
<style>
    /* --- Общая «бумага» (PDF и превью админа) --- */
    .cert-paper {
        position: relative;
        width: 1240px;
        height: 877px;
        box-sizing: border-box;
        padding: 64px 72px;
        overflow: hidden;
        font-family: Manrope, Arial, sans-serif;
        color: #0f172a;
        border: 14px solid #0c7d71;
        box-shadow:
            inset 0 0 0 1px rgba(255, 255, 255, 0.45),
            inset 0 0 80px rgba(13, 148, 136, 0.06);
    }

    .cert-paper__mesh {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background:
            radial-gradient(ellipse 95% 65% at 100% 0%, rgba(20, 184, 166, 0.28) 0%, transparent 52%),
            radial-gradient(ellipse 75% 55% at 0% 100%, rgba(6, 95, 70, 0.2) 0%, transparent 48%),
            radial-gradient(ellipse 50% 40% at 50% 50%, rgba(255, 255, 255, 0.5) 0%, transparent 62%);
    }

    .cert-paper__base {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: linear-gradient(
            152deg,
            #f5fffc 0%,
            #ffffff 38%,
            #eefcf6 72%,
            #dff5eb 100%
        );
    }

    .cert-paper__sheen {
        position: absolute;
        inset: -40%;
        pointer-events: none;
        background: linear-gradient(
            118deg,
            transparent 35%,
            rgba(255, 255, 255, 0.65) 47%,
            rgba(204, 251, 241, 0.35) 52%,
            transparent 65%
        );
        opacity: 0.55;
    }

    .cert-paper__stripes {
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0.11;
        background: repeating-linear-gradient(
            -52deg,
            #0d9488 0px,
            #0d9488 1px,
            transparent 1px,
            transparent 20px
        );
    }

    .cert-paper__stripes2 {
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0.06;
        background: repeating-linear-gradient(
            38deg,
            #065f46 0px,
            #065f46 1px,
            transparent 1px,
            transparent 28px
        );
    }

    .cert-paper__accent-line {
        position: absolute;
        pointer-events: none;
        border-radius: 2px;
    }

    .cert-paper__accent-line--tr {
        top: 96px;
        right: 56px;
        width: 180px;
        height: 4px;
        background: linear-gradient(90deg, transparent, rgba(201, 162, 39, 0.85), transparent);
        transform: rotate(-12deg);
        opacity: 0.85;
    }

    .cert-paper__accent-line--bl {
        bottom: 120px;
        left: 48px;
        width: 220px;
        height: 3px;
        background: linear-gradient(90deg, transparent, rgba(13, 148, 136, 0.55), transparent);
        transform: rotate(-8deg);
    }

    .cert-paper__frame {
        position: absolute;
        inset: 22px;
        border: 2px solid rgba(147, 197, 189, 0.95);
        pointer-events: none;
        border-radius: 2px;
    }

    .cert-paper__frame-glow {
        position: absolute;
        inset: 28px;
        pointer-events: none;
        border: 1px solid rgba(20, 184, 166, 0.22);
        border-radius: 2px;
    }

    .cert-paper__content {
        position: relative;
        z-index: 1;
    }

    /* Сургуч + печать */
    .cert-seal {
        position: absolute;
        right: 56px;
        bottom: 198px;
        width: 122px;
        height: 122px;
        z-index: 2;
        pointer-events: none;
        filter: drop-shadow(0 14px 28px rgba(15, 23, 42, 0.2));
    }

    .cert-seal__wax {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background:
            radial-gradient(circle at 32% 28%, rgba(255, 220, 220, 0.45) 0%, transparent 42%),
            radial-gradient(circle at 70% 72%, rgba(0, 0, 0, 0.28) 0%, transparent 55%),
            radial-gradient(circle at 50% 50%, #b91c1c 0%, #7f1d1d 52%, #450a0a 100%);
        box-shadow:
            inset 0 3px 6px rgba(255, 255, 255, 0.35),
            inset 0 -14px 28px rgba(0, 0, 0, 0.42);
    }

    .cert-seal__ring {
        position: absolute;
        inset: 11px;
        border-radius: 50%;
        border: 2px dashed rgba(255, 255, 255, 0.38);
        opacity: 0.95;
    }

    .cert-seal__star {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 44px;
        font-weight: 800;
        color: rgba(255, 250, 250, 0.94);
        text-shadow: 0 2px 6px rgba(0, 0, 0, 0.45);
        letter-spacing: -0.03em;
        font-family: Manrope, Arial, sans-serif;
    }

    .cert-seal__caption {
        position: absolute;
        left: 50%;
        bottom: -26px;
        transform: translateX(-50%);
        white-space: nowrap;
        font-size: 9px;
        letter-spacing: 0.28em;
        text-transform: uppercase;
        font-weight: 700;
        color: rgba(71, 85, 105, 0.92);
    }

    .cert-seal__ribbon {
        position: absolute;
        left: 50%;
        bottom: -8px;
        transform: translateX(-50%) rotate(-4deg);
        width: 72px;
        height: 14px;
        background: linear-gradient(90deg, #be123c, #9f1239, #be123c);
        border-radius: 2px;
        opacity: 0.88;
        box-shadow: 0 4px 10px rgba(127, 29, 29, 0.35);
    }

    /* Уголковые штрихи */
    .cert-corner {
        position: absolute;
        width: 56px;
        height: 56px;
        pointer-events: none;
        z-index: 1;
        opacity: 0.45;
    }

    .cert-corner--tl {
        top: 36px;
        left: 36px;
        border-top: 3px solid #0f766e;
        border-left: 3px solid #0f766e;
        border-radius: 4px 0 0 0;
    }

    .cert-corner--br {
        bottom: 36px;
        right: 36px;
        border-bottom: 3px solid rgba(201, 162, 39, 0.75);
        border-right: 3px solid rgba(201, 162, 39, 0.75);
        border-radius: 0 0 4px 0;
    }
</style>
