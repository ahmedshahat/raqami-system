<style>
.ct-project-workspace { --pw-ink:#17223b; --pw-muted:#718098; --pw-line:#e5eaf2; --pw-blue:#1769e0; --pw-green:#13b981; --pw-shadow:0 12px 32px rgba(25,54,93,.07); display:grid; gap:22px; color:var(--pw-ink); }
.ct-project-workspace h1,.ct-project-workspace h2,.ct-project-workspace h3,.ct-project-workspace p,.ct-project-workspace dl { margin:0; }
.ct-project-workspace a { text-decoration:none; }
.ct-project-hero,.ct-project-panel,.ct-project-action,.ct-project-stat { background:#fff; border:1px solid var(--pw-line); box-shadow:var(--pw-shadow); }
.ct-project-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:24px; padding:30px; border-radius:22px; overflow:hidden; position:relative; }
.ct-project-hero::before { content:""; position:absolute; inset:0 0 auto; height:5px; background:linear-gradient(90deg,#0d397c,#1769e0,#22b8e6); }
.ct-project-hero__main,.ct-project-hero__actions,.ct-project-hero__facts { position:relative; z-index:1; }
.ct-project-hero__eyebrow,.ct-project-kicker { display:inline-flex; align-items:center; gap:7px; color:var(--pw-blue); font-size:11px; font-weight:800; letter-spacing:.1px; }
.ct-project-hero h1 { margin:9px 0 14px; font-size:clamp(23px,2.2vw,32px); font-weight:800; line-height:1.35; overflow-wrap:anywhere; }
.ct-project-hero__identity { display:flex; flex-wrap:wrap; align-items:center; gap:9px; }
.ct-project-code,.ct-project-state { display:inline-flex; align-items:center; min-height:27px; padding:4px 10px; border-radius:8px; font-size:11px; font-weight:800; }
.ct-project-code { color:#52627a; background:#f2f5f9; direction:ltr; unicode-bidi:isolate; }
.ct-project-state { background:#eaf2ff; color:#1769e0; }
.ct-project-state--active,.ct-project-state--completed { background:#e5f8f1; color:#087a56; }
.ct-project-state--on_hold { background:#fff3da; color:#a86800; }
.ct-project-state--cancelled { background:#feecef; color:#bd354a; }
.ct-project-hero__actions { display:flex; align-items:flex-start; flex-wrap:wrap; justify-content:flex-end; gap:9px; }
.ct-project-button { display:inline-flex; align-items:center; justify-content:center; gap:9px; min-height:42px; padding:10px 16px; border-radius:11px; font-size:12px; font-weight:800; transition:transform .18s,box-shadow .18s,background .18s; white-space:nowrap; }
.ct-project-button:hover,.ct-project-action__link:hover { transform:translateY(-2px); }
.ct-project-button--primary { background:#1769e0; color:#fff; box-shadow:0 8px 18px rgba(23,105,224,.18); }
.ct-project-button--primary:hover,.ct-project-button--primary:focus { background:#0d59c5; color:#fff; }
.ct-project-button--quiet { color:#38516f; background:#f0f5fb; }
.ct-project-button--quiet:hover,.ct-project-button--quiet:focus { color:#1769e0; background:#e9f1fc; }
.ct-project-hero__facts { grid-column:1/-1; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px 24px; padding-top:22px; border-top:1px solid var(--pw-line); }
.ct-project-hero__facts dt { color:var(--pw-muted); font-size:11px; font-weight:700; }
.ct-project-hero__facts dd { margin:5px 0 0; color:#27364f; font-size:13px; font-weight:750; overflow-wrap:anywhere; }
.ct-project-panel { border-radius:19px; overflow:hidden; }
.ct-project-panel__heading,.ct-project-section-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; }
.ct-project-panel__heading { padding:20px 22px; border-bottom:1px solid #edf1f6; }
.ct-project-panel__heading h2,.ct-project-section-heading h2 { margin:5px 0 0; font-size:18px; font-weight:800; }
.ct-project-panel__heading > i { color:#b5c2d2; font-size:20px; }
.ct-project-section-heading { margin-bottom:13px; }
.ct-project-steps { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:0; margin:0; padding:23px 17px 25px; list-style:none; }
.ct-project-step { position:relative; min-width:0; text-align:center; }
.ct-project-step:not(:last-child)::after { content:""; position:absolute; z-index:0; top:21px; right:calc(50% + 23px); width:calc(100% - 46px); height:2px; background:#dbe3ee; }
.ct-project-step.is-complete:not(:last-child)::after { background:#59cdaa; }
.ct-project-step > a,.ct-project-step > div { position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; min-height:100px; padding:0 6px; color:inherit; }
.ct-project-step__number { display:grid; place-items:center; width:43px; height:43px; border:2px solid #dce5f0; border-radius:50%; background:#fff; color:#8795a9; font-size:15px; font-weight:800; transition:.18s; }
.ct-project-step.is-complete .ct-project-step__number { border-color:#13b981; background:#13b981; color:#fff; }
.ct-project-step.is-current .ct-project-step__number { border-color:#1769e0; background:#eaf2ff; color:#1769e0; box-shadow:0 0 0 5px rgba(23,105,224,.08); }
.ct-project-step > a:hover .ct-project-step__number { transform:translateY(-3px); box-shadow:0 8px 16px rgba(23,105,224,.14); }
.ct-project-step__label { display:block; margin-top:10px; font-size:12px; font-weight:800; }
.ct-project-step small { display:block; max-width:170px; margin-top:4px; color:var(--pw-muted); font-size:10px; line-height:1.45; }
.ct-project-step.is-disabled { opacity:.65; }
.ct-project-step.is-disabled > div { cursor:not-allowed; }
.ct-project-next { display:flex; align-items:center; gap:18px; padding:23px; border:1px solid #cfe0f8; border-radius:19px; background:linear-gradient(105deg,#edf5ff,#fff 73%); box-shadow:var(--pw-shadow); }
.ct-project-next__icon { flex:0 0 52px; display:grid; place-items:center; width:52px; height:52px; border-radius:15px; background:#dceaff; color:#1769e0; font-size:22px; }
.ct-project-next__copy { min-width:0; flex:1; }
.ct-project-next h2 { margin:5px 0; font-size:19px; font-weight:800; }
.ct-project-next p { color:#62748d; font-size:12px; line-height:1.6; }
.ct-project-actions { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.ct-project-action { --action:#1769e0; display:flex; flex-direction:column; min-height:201px; padding:19px; border-radius:17px; transition:transform .18s,box-shadow .18s; }
.ct-project-action:hover { transform:translateY(-3px); box-shadow:0 16px 32px rgba(25,54,93,.11); }
.ct-project-action--violet { --action:#7c5ce7; }.ct-project-action--amber { --action:#d58c00; }.ct-project-action--green { --action:#0da877; }
.ct-project-action__top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.ct-project-action__icon { display:grid; place-items:center; width:43px; height:43px; border-radius:12px; color:var(--action); background:color-mix(in srgb,var(--action) 11%,white); font-size:17px; }
.ct-project-action__status { padding:5px 8px; border-radius:999px; color:#53647b; background:#f2f5f9; font-size:10px; font-weight:800; }
.ct-project-action h3 { margin:17px 0 5px; font-size:16px; font-weight:800; }
.ct-project-action p { flex:1; color:var(--pw-muted); font-size:11px; line-height:1.6; }
.ct-project-action__link,.ct-project-action__lock { display:inline-flex; align-items:center; gap:7px; margin-top:15px; font-size:11px; font-weight:800; line-height:1.5; }
.ct-project-action__link { color:var(--action); transition:transform .18s; }
.ct-project-action__link:hover { color:var(--action); }
.ct-project-action__lock { color:#8a98a9; }
.ct-project-action.is-disabled { background:#fbfcfe; }
.ct-project-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.ct-project-stat { display:flex; align-items:center; gap:13px; min-width:0; padding:16px; border-radius:15px; }
.ct-project-stat__icon { flex:0 0 38px; display:grid; place-items:center; width:38px; height:38px; border-radius:10px; color:#1769e0; background:#eaf2ff; }
.ct-project-stat span:not(.ct-project-stat__icon) { display:block; color:var(--pw-muted); font-size:10px; font-weight:700; }
.ct-project-stat strong { display:block; margin-top:4px; color:#183153; font-size:19px; font-weight:800; line-height:1.3; overflow-wrap:anywhere; }
.ct-project-lower { display:grid; grid-template-columns:1fr 1.35fr; gap:15px; align-items:start; }
.ct-project-people,.ct-project-activity { padding:4px 20px 10px; }
.ct-project-person,.ct-project-activity__row { display:flex; align-items:center; gap:11px; padding:13px 0; border-bottom:1px solid #edf1f6; }
.ct-project-person:last-child,.ct-project-activity__row:last-child { border-bottom:0; }
.ct-project-avatar { flex:0 0 37px; display:grid; place-items:center; width:37px; height:37px; border-radius:11px; background:#eaf2ff; color:#1769e0; font-size:15px; font-weight:800; }
.ct-project-person > div,.ct-project-activity__row > div:nth-child(2) { min-width:0; flex:1; }
.ct-project-person strong,.ct-project-person small,.ct-project-activity__row small,.ct-project-activity__row a,.ct-project-activity__row strong { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ct-project-person strong,.ct-project-activity__row a,.ct-project-activity__row strong { color:#263850; font-size:12px; font-weight:800; }
.ct-project-person small,.ct-project-activity__row small { margin-top:3px; color:var(--pw-muted); font-size:10px; }
.ct-project-person__badge { padding:5px 8px; border-radius:7px; background:#e6f8f1; color:#087a56; font-size:10px; font-weight:800; }
.ct-project-empty { margin:8px 0 12px!important; padding:11px 13px; border-radius:10px; background:#f7f9fc; color:var(--pw-muted); font-size:11px; }
.ct-project-activity__icon { flex:0 0 31px; display:grid; place-items:center; width:31px; height:31px; border-radius:9px; color:#1769e0; background:#edf5ff; font-size:12px; }
.ct-project-activity__row a:hover { color:#1769e0; }
.ct-project-activity__meta { flex:0 0 auto; text-align:left; }
.ct-project-activity__meta span,.ct-project-activity__meta time { display:block; color:var(--pw-muted); font-size:10px; }
.ct-project-activity__meta span { color:#3f638b; font-weight:800; }
@media(max-width:1199px) { .ct-project-actions { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:767px) { .ct-project-workspace { gap:17px; }.ct-project-hero { grid-template-columns:1fr; padding:23px; gap:18px; }.ct-project-hero__actions { justify-content:flex-start; }.ct-project-hero__facts { grid-template-columns:repeat(2,minmax(0,1fr)); }.ct-project-next { flex-wrap:wrap; }.ct-project-next .ct-project-button { width:100%; }.ct-project-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }.ct-project-lower { grid-template-columns:1fr; }.ct-project-steps { overflow-x:auto; grid-template-columns:repeat(5,minmax(105px,1fr)); }.ct-project-step small { font-size:9px; } }
@media(max-width:480px) { .ct-project-hero__facts,.ct-project-actions,.ct-project-summary { grid-template-columns:1fr; }.ct-project-hero__actions .ct-project-button { flex:1; }.ct-project-next__icon { display:none; }.ct-project-activity__meta { max-width:90px; } }

</style>
