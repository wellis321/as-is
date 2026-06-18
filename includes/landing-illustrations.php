<?php

declare(strict_types=1);

function landing_illustration_hero(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 420" role="img" aria-label="Structured swimlane process map">
  <defs>
    <linearGradient id="hero-bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f0f6fc"/>
      <stop offset="100%" stop-color="#dceaf5"/>
    </linearGradient>
    <marker id="hero-arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
      <path d="M0,0 L6,3 L0,6 Z" fill="#1a7a96"/>
    </marker>
  </defs>
  <rect width="640" height="420" rx="16" fill="url(#hero-bg)"/>
  <rect x="24" y="24" width="592" height="72" rx="10" fill="#fff3e0" stroke="#d4a574" stroke-width="1.5"/>
  <text x="40" y="50" font-family="Georgia, serif" font-size="15" font-weight="600" fill="#6b4f2a">Tenant</text>
  <rect x="48" y="62" width="96" height="26" rx="6" fill="#fff" stroke="#c9a66b" stroke-width="1.2"/>
  <text x="58" y="79" font-family="system-ui, sans-serif" font-size="12" fill="#333">1. Contact service</text>

  <rect x="24" y="108" width="592" height="148" rx="10" fill="#e8f5e9" stroke="#8fbf98" stroke-width="1.5"/>
  <text x="40" y="134" font-family="Georgia, serif" font-size="15" font-weight="600" fill="#2f5c3a">Customer First</text>
  <rect x="56" y="150" width="108" height="26" rx="6" fill="#fff" stroke="#7cb587"/>
  <text x="66" y="167" font-family="system-ui, sans-serif" font-size="12" fill="#333">2. Take call</text>
  <rect x="188" y="150" width="118" height="26" rx="6" fill="#fff" stroke="#7cb587"/>
  <text x="198" y="167" font-family="system-ui, sans-serif" font-size="12" fill="#333">3. Log in Liberty</text>
  <polygon points="338,163 358,151 358,175" fill="#fff8e1" stroke="#d4a72c" stroke-width="1.2"/>
  <text x="366" y="167" font-family="system-ui, sans-serif" font-size="12" fill="#333">4. Route?</text>
  <rect x="450" y="150" width="98" height="26" rx="6" fill="#fff" stroke="#7cb587"/>
  <text x="460" y="167" font-family="system-ui, sans-serif" font-size="12" fill="#333">5. New job</text>
  <rect x="56" y="196" width="130" height="26" rx="6" fill="#fff" stroke="#7cb587"/>
  <text x="66" y="213" font-family="system-ui, sans-serif" font-size="12" fill="#333">28. GOSS escalation</text>

  <rect x="24" y="272" width="592" height="124" rx="10" fill="#e3f2fd" stroke="#7aaed4" stroke-width="1.5"/>
  <text x="40" y="298" font-family="Georgia, serif" font-size="15" font-weight="600" fill="#2a4f6e">Technical Officer</text>
  <rect x="450" y="314" width="108" height="26" rx="6" fill="#fff" stroke="#7aaed4"/>
  <text x="460" y="331" font-family="system-ui, sans-serif" font-size="12" fill="#333">44. Review form</text>

  <path d="M144,88 C160,118 170,132 188,150" fill="none" stroke="#1a7a96" stroke-width="2" marker-end="url(#hero-arrow)"/>
  <line x1="164" y1="163" x2="186" y2="163" stroke="#1a7a96" stroke-width="2" marker-end="url(#hero-arrow)"/>
  <line x1="306" y1="163" x2="336" y2="163" stroke="#1a7a96" stroke-width="2" marker-end="url(#hero-arrow)"/>
  <line x1="358" y1="175" x2="448" y2="208" stroke="#1a7a96" stroke-width="2" marker-end="url(#hero-arrow)"/>
  <path d="M186,222 C300,260 380,300 450,327" fill="none" stroke="#1a7a96" stroke-width="2" marker-end="url(#hero-arrow)"/>
</svg>
SVG;
}

function landing_illustration_before(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 340" role="img" aria-label="Cluttered traditional process diagram">
  <rect width="520" height="340" rx="12" fill="#faf5f5"/>
  <rect x="20" y="20" width="480" height="36" rx="8" fill="#fff" stroke="#e8d0d0"/>
  <text x="36" y="43" font-family="system-ui, sans-serif" font-size="14" font-weight="600" fill="#9b4545">Traditional AS-IS export</text>
  <g opacity="0.9">
    <rect x="28" y="72" width="62" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="98" y="64" width="54" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="168" y="78" width="68" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="252" y="60" width="58" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="326" y="74" width="64" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="408" y="66" width="56" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="44" y="118" width="70" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="132" y="132" width="52" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="204" y="112" width="76" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="300" y="126" width="60" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="384" y="116" width="68" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="460" y="130" width="48" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="36" y="172" width="58" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="112" y="184" width="72" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="206" y="168" width="64" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="290" y="182" width="56" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="366" y="170" width="74" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="452" y="178" width="52" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="60" y="228" width="66" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="148" y="238" width="54" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="222" y="224" width="78" height="24" rx="4" fill="#fff" stroke="#ccc"/><rect x="318" y="236" width="58" height="24" rx="4" fill="#fff" stroke="#ccc"/>
    <rect x="394" y="226" width="64" height="24" rx="4" fill="#fff" stroke="#ccc"/>
  </g>
  <g stroke="#d8b4b4" stroke-width="1.2" fill="none" opacity="0.8">
    <path d="M90,84 L130,118 M152,76 L198,124 M236,72 L286,126 M360,86 L400,128"/>
    <path d="M98,142 L156,184 M184,124 L236,168 M356,128 L410,178 M420,172 L468,236"/>
    <path d="M128,196 L220,236 M290,194 L360,236"/>
  </g>
  <text x="28" y="310" font-family="system-ui, sans-serif" font-size="12" fill="#8a5a5a">Workshop notes → one static diagram → hard to update</text>
</svg>
SVG;
}

function landing_illustration_after(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 340" role="img" aria-label="Structured editable process map">
  <rect width="520" height="340" rx="12" fill="#f2f8f5"/>
  <rect x="20" y="20" width="480" height="36" rx="8" fill="#fff" stroke="#b8d9c8"/>
  <text x="36" y="43" font-family="system-ui, sans-serif" font-size="14" font-weight="600" fill="#2a6e55">AS-IS Management</text>
  <rect x="24" y="68" width="472" height="62" rx="8" fill="#e8f5e9" stroke="#9cc9a5" stroke-width="1.2"/>
  <text x="40" y="90" font-family="Georgia, serif" font-size="13" fill="#2f5c3a">Swimlane · Customer First</text>
  <rect x="40" y="98" width="78" height="22" rx="5" fill="#fff" stroke="#7cb587"/><rect x="132" y="98" width="96" height="22" rx="5" fill="#fff" stroke="#7cb587"/>
  <rect x="242" y="98" width="84" height="22" rx="5" fill="#fff" stroke="#7cb587"/>

  <rect x="24" y="142" width="472" height="88" rx="8" fill="#fff8e1" stroke="#e0c878" stroke-width="1.2"/>
  <text x="40" y="164" font-family="Georgia, serif" font-size="13" fill="#6b5620">Swimlane · Approver</text>
  <rect x="40" y="172" width="88" height="22" rx="5" fill="#fff" stroke="#d4a72c"/><rect x="144" y="172" width="104" height="22" rx="5" fill="#fff" stroke="#d4a72c"/>
  <polygon points="270,183 286,173 286,193" fill="#fff" stroke="#d4a72c"/><rect x="300" y="172" width="80" height="22" rx="5" fill="#fff" stroke="#d4a72c"/>

  <rect x="24" y="242" width="472" height="62" rx="8" fill="#e3f2fd" stroke="#9ec0de" stroke-width="1.2"/>
  <text x="40" y="264" font-family="Georgia, serif" font-size="13" fill="#2a4f6e">Swimlane · Finance</text>
  <rect x="40" y="272" width="92" height="22" rx="5" fill="#fff" stroke="#7aaed4"/><rect x="148" y="272" width="100" height="22" rx="5" fill="#fff" stroke="#7aaed4"/>

  <line x1="118" y1="109" x2="130" y2="109" stroke="#1a7a96" stroke-width="2"/>
  <line x1="228" y1="109" x2="240" y2="109" stroke="#1a7a96" stroke-width="2"/>
  <line x1="128" y1="183" x2="142" y2="183" stroke="#1a7a96" stroke-width="2"/>
  <text x="360" y="280" font-family="system-ui, sans-serif" font-size="12" font-weight="600" fill="#2a6e55">↻ Live diagram</text>
  <text x="28" y="322" font-family="system-ui, sans-serif" font-size="12" fill="#5a7a6a">Editable lanes, steps, systems &amp; connections</text>
</svg>
SVG;
}
