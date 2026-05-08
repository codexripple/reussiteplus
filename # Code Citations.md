# Code Citations

## License: unknown
https://github.com/richardtape/content-visibility/blob/7d94775fc68f6c21bfd7f0ab0d3ee1cfd028c2bf/src/editor.scss

```
<parameter name="newString"><style>
/* ── DESIGN TOKENS – identiques à index.php ── */
:root {
  --primary:       #007A5E;
  --primary-dark:  #005A45;
  --primary-light: #00A97F;
  --primary-subtle:#E8F5F1;
  --gold:          #C9972A;
  --gold-light:    #F5E6C0;
  --rouge:         #C9342A;
  --bleu:          #1E5FAD;
  --noir:          #0D1117;
  --gris-900:      #1C2433;
  --gris-800:      #2E3A4A;
  --gris-700:      #4A5568;
  --gris-600:      #6B7280;
  --gris-500:      #9CA3AF;
  --gris-400:      #CBD5E1;
  --gris-200:      #E2E8F0;
  --gris-100:      #F1F5F9;
  --gris-50:       #F8FAFC;
  --blanc:         #FFFFFF;
  --font-display:  'Syne', sans-serif;
  --font-body:     'Manrope', system-ui, sans-serif;
  --radius:        10px;
  --radius-lg:     16px;
  --radius-xl:     24px;
  --shadow:        0 4px 16px rgba(0,0,0,.08);
  --shadow-lg:     0 8px 32px rgba(0,0,0,.12);
  --shadow-glow:   0 0 40px rgba(0,122,94,.25);
  --transition:    200ms cubic-bezier(.4,0,.2,1);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: var(--font-body);
  background: var(--gris-50);
  color: var(--gris-900);
  font-size: 16px;
  line-height: 1.6;
  overflow-x: hidden;
}
a { text-decoration: none; color: inherit; }
img { display: block; max-width: 100%; }

/* ── NAV (identique index.php) ── */
.nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(13,17,23,.96); backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,.07);
  padding: 0 40px; height: 68px;
  display: flex; align-items: center; gap: 32px;
}
.nav-logo {
  display: flex; align-items: center; gap: 10px;
  font-family: var(--font-display); font-size: 22px; font-weight: 800;
  color: white; letter-spacing: -.5px;
}
.nav-logo .lplus { color: var(--gold); }
.nav-links { display: flex; gap: 28px; flex: 1; margin-left: 12px; }
.nav-link { font-size: 14px; color: rgba(255,255,255,.65); transition: var(--transition); font-weight: 500; }
.nav-link:hover, .nav-link.active { color: white; }
.nav-actions { display: flex; gap: 12px; align-items: center; }

/* ── BOUTONS ── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 20px; border-radius: var(--radius);
  font-size: 14px; font-weight: 600; font-family: var(--font-body);
  cursor: pointer; border: none; transition: var(--transition);
}
.btn-primary  { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); box-shadow: var(--shadow-glow); }
.btn-outline  { background: transparent; color: white; border: 1px solid rgba(255,255,255,.25); }
.btn-outline:hover { background: rgba(255,255,255,.08); }

/* ── HERO CONTACT ── */
.hero-contact {
  background: var(--noir); padding: 128px 40px 80px;
  position: relative; overflow: hidden;
}
.hero-contact::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,122,94,.2) 0%, transparent 65%);
  pointer-events: none;
}
.hero-contact-inner { position: relative; max-width: 700px; }
.hero-eyebrow {
  font-size: 11px; font-weight: 700; letter-spacing: 3px;
  text-transform: uppercase; color: var(--primary-light); margin-bottom: 24px;
  display: flex; align-items: center; gap: 8px;
}
.hero-contact h1 {
  font-family: var(--font-display);
  font-size: clamp(40px, 6vw, 68px);
  font-weight: 900; color: white; line-height: 1.05;
  letter-spacing: -1.5px; margin-bottom: 20px;
}
.hero-contact h1 em { font-style: italic; color: var(--gold); }
.hero-contact p {
  font-size: 18px; color: rgba(255,255,255,.6); line-height: 1.7; max-width: 520px;
}

/* ── LAYOUT FORM+ASIDE ── */
.contact-main {
  max-width: 1200px; margin: 0 auto;
  padding: 72px 40px 80px;
  display: grid; grid-template-columns: 1.6fr 1fr; gap: 64px; align-items: start;
}

/* ── FORM ── */
.form-heading {
  font-family: var(--font-display); font-size: 28px; font-weight: 800;
  color: var(--gris-900); margin-bottom: 10px; letter-spacing: -.5px;
}
.form-sub { font-size: 14px; color: var(--gris-600); margin-bottom: 36px; }

.field-group { margin-bottom: 22px; }
.field-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.field-label {
  display: block; font-size: 12px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 1px;
  color: var(--gris-700); margin-bottom: 8px;
}
.field-req { color: var(--rouge); margin-left: 2px; }
.field-input {
  width: 100%; padding: 13px 16px;
  border: 1.5px solid var(--gris-200); border-radius: var(--radius);
  background: var(--blanc); font-family: var(--font-body); font-size: 15px;
  color: var(--gris-900); outline: none; transition: var(--transition);
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,122,94,.12); }
.field-input.is-error { border-color: var(--rouge); }
textarea.field-input { resize: vertical; min-height: 160px; line-height: 1.6; }
select.field-input {
  appearance: none; cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 16px center;
}
.char-count { font-size: 11px; color: var(--gris-500); text-align: right; margin-top: 6px; }

.btn-submit {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--primary); color: white; border: none; cursor: pointer;
  padding: 15px 32px; border-radius: var(--radius);
  font-family: var(--font-body); font-size: 14px; font-weight: 700;
  letter-spacing: .8px; text-transform: uppercase; transition: var(--transition);
}
.btn-submit:hover { background: var(--primary-dark); box-shadow: var(--shadow-glow); }
.btn-submit:disabled { opacity: .6; cursor: not-allowed; }

.form-privacy { font-size: 12px; color: var(--gris-500); margin-top: 14px; display: flex; align-items: center; gap: 6px; }

/* ── ALERTS ── */
.alert {
  padding: 14px 18px; border-radius: var(--radius); font-size: 14px;
  margin-bottom: 24px; display: flex; align-items: flex-start; gap: 10px;
  border-left: 3px solid;
}
.alert-error  { background: #FEF0EF; border-color: var(--rouge); color: #991b1b; }
.errors-list  { list-style: none; }
.errors-list li::before { content: "— "; }

/* ── ASIDE ── */
.aside-cta {
  background: var(--noir); color: white;
  border-radius: var(--radius-xl); padding: 36px;
}
.aside-eyebrow {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 3px; color: rgba(255,255,255,.35); margin-bottom: 14px;
}
.aside-title {
  font-family: var(--font-display); font-size: 22px; font-weight: 800;
  color: white; letter-spacing: -.5px; line-height: 1.2; margin-bottom: 12px;
}
.aside-title em { font-style: italic; color: var(--primary-light); }
.aside-p { font-size: 14px; color: rgba(255,255,255,.55); line-height: 1.7; margin-bottom: 24px; }
.aside-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--primary); color: white; border-radius: var(--radius);
  padding: 13px 24px; font-size: 13px; font-weight: 700;
  letter-spacing: .6px; text-transform: uppercase; transition: var(--transition);
}
.aside-btn:hover { background: var(--primary-dark); }

.contact-info { margin-top: 28px; }
.info-item {
  display: flex; gap: 14px; padding: 16px 0;
  border-bottom: 1px solid var(--gris-200);
}
.info-item:last-child { border-bottom: none; }
.info-ico { font-size: 16px; color: var(--primary); flex-shrink: 0; margin-top: 2px; }
.info-lbl {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 2px; color: var(--gris-500); margin-bottom: 4px;
}
.info-val { font-size: 14px; color: var(--gris-800); line-height: 1.6; }
.info-val a { color: var(--primary); font-weight: 600; }

.aside-loc {
  background: var(--noir); border-radius: var(--radius-lg);
  padding: 20px 24px; margin-top: 16px;
}
.aside-loc-label {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 3px; color: rgba(255,255,255,.35); margin-bottom: 8px;
  display: flex; align-items: center; gap: 8px;
}
.aside-loc-city { font-size: 20px; font-weight: 700; color: white; font-family: var(--font-display); }
.aside-loc-sub { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,.35); margin-top: 4px; }

/* ── FAQ ── */
.faq-section { background: var(--gris-100); padding: 80px 40px; }
.faq-wrap    { max-width: 1200px; margin: 0 auto; }
.faq-heading {
  font-family: var(--font-display); font-size: clamp(32px, 4.5vw, 52px);
  font-weight: 900; color: var(--gris-900); letter-spacing: -1px;
  line-height: 1.1; margin-bottom: 10px;
}
.faq-heading em { font-style: italic; color: var(--primary); }
.faq-sub-p { font-size: 15px; color: var(--gris-600); margin-bottom: 48px; }
.faq-grid { display: grid; grid-template-columns: 1fr 1fr; }
.faq-item { border-bottom: 1px solid var(--gris-200); padding-right: 32px; }
.faq-item:nth-child(even) { padding-right: 0; padding-left: 32px; border-left: 1px solid var(--gris-200); }
.faq-q {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 22px 0; cursor: pointer; font-size: 15px; font-weight: 600;
  color: var(--gris-900);
}
.faq-q-icon {
  width: 26px; height: 26px; border-radius: 50%;
  border: 1.5px solid var(--gris-200); display: flex; align-items: center;
  justify-content: center; flex-shrink: 0; font-size: 16px;
  color: var(--gris-600); transition: var(--transition); line-height: 1;
}
.faq-item.open .faq-q-icon { background: var(--primary); border-color: var(--primary); color: white; transform: rotate(45deg); }
.faq-a {
  font-size: 14px; color: var(--gris-600); line-height: 1.8;
  max-height: 0; overflow: hidden; transition: max-height .3s ease, padding .3s ease;
}
.faq-item.open .faq-a { max-height: 240px; padding-bottom: 22px; }

/* ── CTA ── */
.cta-section {
  background: var(--noir); padding: 80px 40px; text-align: center;
  position: relative; overflow: hidden;
}
.cta-section::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 50%, rgba(0,122,94,.18) 0%, transparent 70%);
  pointer-events: none;
}
.cta-inner { position: relative; max-width: 620px; margin: 0 auto; }
.cta-eyebrow {
  font-size: 11px; font-weight: 700; letter-spacing: 3px;
  text-transform: uppercase; color: rgba(255,255,255,.35); margin-bottom: 24px;
}
.cta-title {
  font-family: var(--font-display);
  font-size: clamp(32px, 5vw, 52px);
  font-weight: 900; color: white; line-height: 1.1;
  letter-spacing: -1px; margin-bottom: 16px;
}
.cta-title em { font-style: italic; color: var(--primary-light); }
.cta-p { font-size: 16px; color: rgba(255,255,255,.5); line-height: 1.7; margin-bottom: 36px; }
.cta-actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
.btn-cta-primary {
  background: var(--primary); color: white; border-radius: var(--radius);
  padding: 15px 32px; font-size: 14px; font-weight: 700;
  letter-spacing: .6px; text-transform: uppercase; transition: var(--transition);
  display: inline-flex; align-items: center; gap: 8px;
}
.btn-cta-primary:hover { background: var(--primary-dark); box-shadow: var(--shadow-glow); }
.btn-cta-ghost {
  background: transparent; color: rgba(255,255,255,.7);
  border: 1px solid rgba(255,255,255,.25); border-radius: var(--radius);
  padding: 15px 32px; font-size: 14px; font-weight: 600;
  display: inline-flex; align-items: center; gap: 8px; transition: var(--transition);
}
.btn-cta-ghost:hover { color: white; border-color: white; }

/* ── SUCCESS ── */
.success-section { max-width: 1200px; margin: 0 auto; padding: 120px 40px 80px; }
.success-check {
  width: 72px; height: 72px; border-radius: 50%;
  border: 2px solid var(--primary); display: flex; align-items: center;
  justify-content: center; margin
```

