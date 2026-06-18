<?php

declare(strict_types=1);

function landing_illustration_hero(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" role="img" aria-label="Structured swimlane process map">
  <defs>
    <linearGradient id="hero-bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#f0f6fc"/>
      <stop offset="100%" stop-color="#dceaf5"/>
    </linearGradient>
    <marker id="hero-arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto" markerUnits="userSpaceOnUse">
      <polygon points="0 0, 8 4, 0 8" fill="#1a7a96"/>
    </marker>
  </defs>

  <rect width="640" height="400" rx="16" fill="url(#hero-bg)"/>

  <!-- Swimlanes -->
  <rect x="20" y="20" width="600" height="78" rx="10" fill="#fff3e0" stroke="#d4a574" stroke-width="1.5"/>

  <rect x="20" y="106" width="600" height="148" rx="10" fill="#e8f5e9" stroke="#8fbf98" stroke-width="1.5"/>

  <rect x="20" y="262" width="600" height="118" rx="10" fill="#e3f2fd" stroke="#7aaed4" stroke-width="1.5"/>

  <!-- Arrows (drawn first; boxes and labels sit on top) -->
  <g fill="none" stroke="#1a7a96" stroke-width="2">
    <path d="M 150 82 L 150 125 L 80 125 L 80 150"/>
    <line x1="120" y1="170" x2="136" y2="170" marker-end="url(#hero-arrow)"/>
    <line x1="246" y1="170" x2="262" y2="170" marker-end="url(#hero-arrow)"/>
    <line x1="310" y1="170" x2="368" y2="170" marker-end="url(#hero-arrow)"/>
    <path d="M 286 184 L 286 200 L 102 200 L 102 206"/>
    <path d="M 102 240 L 102 300 L 418 300 L 418 304"/>
  </g>

  <!-- Steps -->
  <rect id="b1" x="100" y="54" width="100" height="28" rx="6" fill="#fff" stroke="#c9a66b" stroke-width="1.2"/>
  <rect id="b2" x="40" y="156" width="80" height="28" rx="6" fill="#fff" stroke="#7cb587"/>
  <rect id="b3" x="136" y="156" width="110" height="28" rx="6" fill="#fff" stroke="#7cb587"/>
  <polygon id="b4" points="286,156 310,170 286,184 262,170" fill="#fff8e1" stroke="#d4a72c" stroke-width="1.2"/>
  <rect id="b5" x="368" y="156" width="76" height="28" rx="6" fill="#fff" stroke="#7cb587"/>
  <rect id="b28" x="40" y="212" width="124" height="28" rx="6" fill="#fff" stroke="#7cb587"/>
  <rect id="b44" x="368" y="310" width="100" height="28" rx="6" fill="#fff" stroke="#7aaed4"/>

  <!-- Downward arrowheads (after boxes — line stops at base, tip touches box top) -->
  <g fill="#1a7a96">
    <polygon points="80,156 76,150 84,150"/>
    <polygon points="102,212 98,206 106,206"/>
    <polygon points="418,310 414,304 422,304"/>
  </g>

  <!-- Step labels (centred inside each shape) -->
  <text x="150" y="72" text-anchor="middle" font-family="system-ui, sans-serif" font-size="11" fill="#333">1. Contact</text>
  <text x="80" y="174" text-anchor="middle" font-family="system-ui, sans-serif" font-size="11" fill="#333">2. Take call</text>
  <text x="191" y="174" text-anchor="middle" font-family="system-ui, sans-serif" font-size="11" fill="#333">3. Log in Liberty</text>
  <text x="286" y="174" text-anchor="middle" font-family="system-ui, sans-serif" font-size="10" fill="#6b5620">Route?</text>
  <text x="406" y="174" text-anchor="middle" font-family="system-ui, sans-serif" font-size="11" fill="#333">5. New job</text>
  <text x="102" y="230" text-anchor="middle" font-family="system-ui, sans-serif" font-size="11" fill="#333">28. GOSS escalation</text>
  <text x="418" y="328" text-anchor="middle" font-family="system-ui, sans-serif" font-size="11" fill="#333">44. Review form</text>

  <!-- Lane titles (top-right, drawn last) -->
  <text x="606" y="38" text-anchor="end" font-family="Georgia, serif" font-size="13" font-weight="600" fill="#6b4f2a">Tenant</text>
  <text x="606" y="118" text-anchor="end" font-family="Georgia, serif" font-size="13" font-weight="600" fill="#2f5c3a">Customer First</text>
  <text x="606" y="274" text-anchor="end" font-family="Georgia, serif" font-size="13" font-weight="600" fill="#2a4f6e">Technical Officer</text>
</svg>
SVG;
}

function landing_illustration_before(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 320" role="img" aria-label="Cluttered traditional process diagram">
  <defs>
    <marker id="before-arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
      <polygon points="0 0, 6 3, 0 6" fill="#c9a0a0"/>
    </marker>
  </defs>
  <rect width="520" height="320" rx="12" fill="#faf5f5"/>
  <rect x="16" y="16" width="488" height="32" rx="8" fill="#fff" stroke="#e8d0d0"/>
  <text x="30" y="37" font-family="system-ui, sans-serif" font-size="13" font-weight="600" fill="#9b4545">Traditional AS-IS export</text>

  <!-- Messy but connected boxes -->
  <rect x="24" y="62" width="56" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="92" y="56" width="50" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="156" y="68" width="60" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="232" y="54" width="52" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="300" y="66" width="58" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="374" y="58" width="54" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="440" y="70" width="48" height="22" rx="4" fill="#fff" stroke="#ccc"/>

  <rect x="36" y="104" width="64" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="118" y="116" width="48" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="184" y="100" width="70" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="272" y="112" width="54" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="344" y="104" width="62" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="422" y="118" width="46" height="22" rx="4" fill="#fff" stroke="#ccc"/>

  <rect x="28" y="152" width="52" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="96" y="162" width="68" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="180" y="148" width="58" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="256" y="160" width="52" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="324" y="150" width="66" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="406" y="156" width="50" height="22" rx="4" fill="#fff" stroke="#ccc"/>

  <rect x="48" y="200" width="60" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="128" y="208" width="50" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="198" y="196" width="72" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="286" y="206" width="54" height="22" rx="4" fill="#fff" stroke="#ccc"/>
  <rect x="356" y="198" width="60" height="22" rx="4" fill="#fff" stroke="#ccc"/>

  <!-- Lines connecting box edges (chaotic but attached) -->
  <g fill="none" stroke="#d4a0a0" stroke-width="1.3" marker-end="url(#before-arrow)" opacity="0.85">
    <line x1="80" y1="73" x2="92" y2="67"/>
    <line x1="142" y1="67" x2="156" y2="73"/>
    <line x1="216" y1="79" x2="232" y2="65"/>
    <line x1="284" y1="65" x2="300" y2="73"/>
    <line x1="358" y1="69" x2="374" y2="69"/>
    <line x1="428" y1="69" x2="440" y2="81"/>
    <line x1="68" y1="84" x2="68" y2="104"/>
    <line x1="100" y1="126" x2="118" y2="127"/>
    <line x1="166" y1="127" x2="184" y2="111"/>
    <line x1="254" y1="111" x2="272" y2="123"/>
    <line x1="326" y1="115" x2="344" y2="115"/>
    <line x1="406" y1="115" x2="422" y2="129"/>
    <line x1="80" y1="174" x2="96" y2="173"/>
    <line x1="164" y1="173" x2="180" y2="159"/>
    <line x1="238" y1="159" x2="256" y2="171"/>
    <line x1="308" y1="171" x2="324" y2="161"/>
    <line x1="390" y1="167" x2="406" y2="167"/>
    <line x1="108" y1="218" x2="128" y2="219"/>
    <line x1="178" y1="219" x2="198" y2="207"/>
    <line x1="270" y1="207" x2="286" y2="217"/>
    <line x1="340" y1="209" x2="356" y2="209"/>
  </g>

  <text x="24" y="268" font-family="system-ui, sans-serif" font-size="11" fill="#8a5a5a">Workshop notes → one static diagram → hard to update</text>
</svg>
SVG;
}

function landing_illustration_after(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 320" role="img" aria-label="Structured editable process map">
  <defs>
    <marker id="after-arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto" markerUnits="userSpaceOnUse">
      <polygon points="0 0, 8 4, 0 8" fill="#1a7a96"/>
    </marker>
  </defs>
  <rect width="520" height="320" rx="12" fill="#f2f8f5"/>
  <rect x="16" y="16" width="488" height="32" rx="8" fill="#fff" stroke="#b8d9c8"/>
  <text x="30" y="37" font-family="system-ui, sans-serif" font-size="13" font-weight="600" fill="#2a6e55">AS-IS Management</text>

  <rect x="20" y="60" width="480" height="58" rx="8" fill="#e8f5e9" stroke="#9cc9a5" stroke-width="1.2"/>
  <rect x="34" y="88" width="72" height="22" rx="5" fill="#fff" stroke="#7cb587"/>
  <rect x="124" y="88" width="88" height="22" rx="5" fill="#fff" stroke="#7cb587"/>
  <rect x="230" y="88" width="80" height="22" rx="5" fill="#fff" stroke="#7aaed4"/>

  <rect x="20" y="128" width="480" height="72" rx="8" fill="#fff8e1" stroke="#e0c878" stroke-width="1.2"/>
  <rect x="34" y="156" width="88" height="22" rx="5" fill="#fff" stroke="#d4a72c"/>
  <rect x="142" y="156" width="100" height="22" rx="5" fill="#fff" stroke="#d4a72c"/>
  <rect x="262" y="156" width="88" height="22" rx="5" fill="#fff" stroke="#d4a72c"/>

  <rect x="20" y="212" width="480" height="58" rx="8" fill="#e3f2fd" stroke="#9ec0de" stroke-width="1.2"/>
  <rect x="34" y="240" width="84" height="22" rx="5" fill="#fff" stroke="#7aaed4"/>
  <rect x="138" y="240" width="92" height="22" rx="5" fill="#fff" stroke="#7aaed4"/>

  <g fill="none" stroke="#1a7a96" stroke-width="2">
    <line x1="106" y1="99" x2="124" y2="99" marker-end="url(#after-arrow)"/>
    <line x1="212" y1="99" x2="230" y2="99" marker-end="url(#after-arrow)"/>
    <line x1="122" y1="167" x2="142" y2="167" marker-end="url(#after-arrow)"/>
    <line x1="242" y1="167" x2="262" y2="167" marker-end="url(#after-arrow)"/>
    <path d="M 192 178 L 192 196 L 76 196 L 76 234"/>
  </g>
  <polygon points="76,240 72,234 80,234" fill="#1a7a96"/>

  <!-- Lane titles (top-right, above flow lines) -->
  <text x="486" y="76" text-anchor="end" font-family="Georgia, serif" font-size="12" font-weight="600" fill="#2f5c3a">Customer First</text>
  <text x="486" y="144" text-anchor="end" font-family="Georgia, serif" font-size="12" font-weight="600" fill="#6b5620">Approver</text>
  <text x="486" y="228" text-anchor="end" font-family="Georgia, serif" font-size="12" font-weight="600" fill="#2a4f6e">Finance</text>

  <text x="340" y="252" font-family="system-ui, sans-serif" font-size="11" font-weight="600" fill="#2a6e55">↻ Live diagram</text>
  <text x="24" y="296" font-family="system-ui, sans-serif" font-size="11" fill="#5a7a6a">Editable lanes, steps, systems &amp; connections</text>
</svg>
SVG;
}
