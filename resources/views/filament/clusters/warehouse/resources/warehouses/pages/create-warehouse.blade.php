<x-filament-panels::page>
    <style>
        .warehouse-create-page .fi-page-header {
            margin-bottom: 1rem;
        }

        .warehouse-create-page .fi-page-content {
            max-width: 1480px;
            margin-inline: auto;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .warehouse-create-page .warehouse-create-hero {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 1.75rem;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.28), transparent 22rem),
                linear-gradient(135deg, #0f766e 0%, #0284c7 48%, #eff6ff 100%);
            color: #ffffff;
            box-shadow: 0 24px 60px rgba(2, 132, 199, 0.18);
        }

        .warehouse-create-page .warehouse-create-hero::after {
            content: "";
            position: absolute;
            inset: auto -8% -42% auto;
            width: 22rem;
            height: 22rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.14);
            filter: blur(8px);
        }

        .warehouse-create-page .warehouse-create-hero__eyebrow {
            margin: 0 0 0.65rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
        }

        .warehouse-create-page .warehouse-create-hero__title {
            margin: 0;
            max-width: 44rem;
            font-size: clamp(1.6rem, 2vw, 2.35rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .warehouse-create-page .warehouse-create-hero__text {
            margin: 0.85rem 0 0;
            max-width: 46rem;
            font-size: 0.98rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.88);
        }

        .warehouse-create-page .warehouse-create-hero__chips {
            position: relative;
            z-index: 1;
            margin-top: 1.1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
        }

        .warehouse-create-page .warehouse-create-hero__chip {
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 9999px;
            padding: 0.5rem 0.85rem;
            background: rgba(255, 255, 255, 0.12);
            font-size: 0.82rem;
            font-weight: 600;
            color: #ffffff;
            backdrop-filter: blur(10px);
        }

        .warehouse-create-page .warehouse-create-shell {
            position: relative;
            border-radius: 28px;
            padding: 1.25rem;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.98));
            box-shadow: 0 30px 70px rgba(15, 23, 42, 0.07);
        }

        .warehouse-create-page .warehouse-create-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            border: 1px solid rgba(148, 163, 184, 0.18);
            pointer-events: none;
        }

        .warehouse-create-page .fi-sc-section {
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .warehouse-create-page .fi-section {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff, #fbfdff);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.05);
        }

        .warehouse-create-page .fi-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(14, 116, 144, 0.08);
        }

        .warehouse-create-page .fi-section-header {
            padding: 1rem 1.1rem;
            background: linear-gradient(180deg, rgba(240, 249, 255, 0.95), rgba(255, 255, 255, 0.96));
            border-bottom: 1px solid rgba(186, 230, 253, 0.65);
        }

        .warehouse-create-page .fi-section-header-heading {
            font-size: 0.98rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }

        .warehouse-create-page .fi-section-header-description {
            margin-top: 0.28rem;
            max-width: 30rem;
            font-size: 0.82rem;
            line-height: 1.55;
            color: #475569;
        }

        .warehouse-create-page .fi-section-content {
            padding: 1.1rem;
        }

        .warehouse-create-page [data-field-wrapper] label,
        .warehouse-create-page [data-field-wrapper] .fi-fo-field-wrp-label {
            font-size: 0.79rem;
            font-weight: 700;
            color: #334155;
            letter-spacing: 0.01em;
        }

        .warehouse-create-page .fi-input-wrp,
        .warehouse-create-page .fi-select-input,
        .warehouse-create-page .choices,
        .warehouse-create-page .fi-input {
            border-radius: 16px;
        }

        .warehouse-create-page .fi-input-wrp {
            border-color: rgba(148, 163, 184, 0.22);
            background: #ffffff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .warehouse-create-page .fi-input,
        .warehouse-create-page textarea.fi-input,
        .warehouse-create-page .fi-select-input {
            min-height: 2.9rem;
        }

        .warehouse-create-page .fi-input:focus,
        .warehouse-create-page .fi-select-input:focus,
        .warehouse-create-page .fi-input-wrp:focus-within {
            border-color: rgba(2, 132, 199, 0.34);
            box-shadow:
                0 0 0 4px rgba(14, 165, 233, 0.12),
                0 12px 22px rgba(14, 165, 233, 0.08);
        }

        .warehouse-create-page .fi-fo-field-wrp-error-message,
        .warehouse-create-page .fi-fo-field-wrp-error-list {
            font-size: 0.78rem;
        }

        .warehouse-create-page .fi-section-content .fi-btn {
            border-radius: 9999px;
            font-weight: 700;
        }

        .warehouse-create-page .fi-btn-color-primary {
            box-shadow: 0 12px 28px rgba(14, 165, 233, 0.2);
        }

        .warehouse-create-page .fi-section .fi-btn-color-gray {
            background: #f8fafc;
        }

        .warehouse-create-page .fi-fo-repeater-item {
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }

        .warehouse-create-page .fi-fo-repeater-add-between-button-ctn,
        .warehouse-create-page .fi-fo-repeater-add-action-ctn {
            justify-content: center;
        }

        .warehouse-create-page .fi-section-content [data-alignment="end"] {
            padding-top: 0.35rem;
        }

        @media (max-width: 1024px) {
            .warehouse-create-page .warehouse-create-shell {
                padding: 0.85rem;
                border-radius: 22px;
            }

            .warehouse-create-page .warehouse-create-hero {
                padding: 1.25rem;
                border-radius: 22px;
            }
        }

        @media (max-width: 768px) {
            .warehouse-create-page .fi-page-content {
                gap: 1rem;
            }

            .warehouse-create-page .warehouse-create-hero__chips {
                gap: 0.5rem;
            }

            .warehouse-create-page .warehouse-create-hero__chip {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
        }
    </style>

    <section class="warehouse-create-hero">
        <p class="warehouse-create-hero__eyebrow">Warehouse Setup</p>
        <h2 class="warehouse-create-hero__title">Tao kho moi ro rang, gon mat va de nhap lieu hon</h2>
        <p class="warehouse-create-hero__text">
            Toan bo thong tin kho, dia chi, cau hinh giao hang va ton dau duoc nhom lai thanh tung khoi ro nghia
            de thao tac nhanh hon va it roi mat hon.
        </p>

        <div class="warehouse-create-hero__chips">
            <span class="warehouse-create-hero__chip">Thong tin co ban</span>
            <span class="warehouse-create-hero__chip">Dia chi giao hang</span>
            <span class="warehouse-create-hero__chip">Quan ly va nguoi gui</span>
            <span class="warehouse-create-hero__chip">Ton kho ban dau</span>
        </div>
    </section>

    <div class="warehouse-create-shell">
        {{ $this->content }}
    </div>
</x-filament-panels::page>
