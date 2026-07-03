<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
require_min_role('admin');

ob_start();
?>
<style>
.doc-section {
    counter-reset: doc-section;
}
.doc-block {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.doc-block-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem 0.85rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
}
.doc-block-header h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
    color: var(--text);
}
.doc-block-header .doc-subtitle {
    font-size: 0.8rem;
    color: var(--muted);
    margin: 0.15rem 0 0;
    font-weight: 400;
}
.doc-body {
    padding: 1.25rem;
    font-size: 0.9rem;
    line-height: 1.65;
    color: var(--text);
}
.doc-body h3 {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--muted);
    margin: 1.4rem 0 0.5rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid var(--border);
}
.doc-body h3:first-child { margin-top: 0; }
.doc-body ul {
    margin: 0.4rem 0 0.8rem 1.1rem;
    padding: 0;
}
.doc-body li { margin-bottom: 0.25rem; }
.doc-body p { margin: 0 0 0.65rem; }
.doc-body p:last-child { margin-bottom: 0; }
.doc-body .priority-item {
    display: grid;
    grid-template-columns: 24px 1fr;
    gap: 0.4rem 0.6rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--border);
}
.doc-body .priority-item:last-child { border-bottom: none; }
.priority-num {
    width: 22px; height: 22px;
    background: var(--accent);
    color: #fff;
    border-radius: 50%;
    font-size: 0.72rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 0.1rem;
}
.gov-tier {
    border: 1px solid var(--border);
    border-radius: var(--r);
    margin-bottom: 0.65rem;
    overflow: hidden;
}
.gov-tier-head {
    background: var(--bg);
    padding: 0.5rem 0.75rem;
    font-weight: 700;
    font-size: 0.85rem;
    display: flex; align-items: center; gap: 0.5rem;
    border-bottom: 1px solid var(--border);
}
.gov-tier-body {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}
.gov-tier-body ul { margin: 0.2rem 0 0 1rem; padding: 0; }
.gov-tier-body li { margin-bottom: 0.15rem; }
.tier-badge {
    font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em;
    padding: 0.1rem 0.45rem; border-radius: 3px;
    background: #e8edf4; color: #374151;
}
</style>

<header>
    <div>
        <h1>Boards &amp; Groups</h1>
        <p>Supporting documents for project governance boards and working groups.</p>
    </div>
</header>

<div class="doc-section">

<!-- ── Doc 1: Project Owner Summary ──────────────────────────────────── -->
<div class="doc-block" id="project-owner-summary">
    <div class="doc-block-header">
        <div>
            <h2>Project Owner Summary</h2>
            <p class="doc-subtitle">Executive overview — Repairs Digital Transformation</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="copyDoc('doc1')">Copy</button>
    </div>
    <div class="doc-body" id="doc1">

        <h3>Project</h3>
        <p><strong>Repairs – Digital Transformation (Mobile Working &amp; Servitor Replacement)</strong><br>
        Timeline: January 2026 – October 2027<br>
        Project Owner: Housing Services Manager (Strategic Change, Planning &amp; Support)<br>
        Business Owner: Housing Services Manager (Repairs and Property Maintenance)</p>

        <h3>Purpose</h3>
        <p>This project will modernise the Housing Repairs service by introducing mobile-enabled, end-to-end digital processes and replacing the legacy Servitor system before it becomes unsupported in 2027.</p>

        <h3>Strategic Intent</h3>
        <ul>
            <li>Transform repairs delivery into a real-time, data-driven, customer-focused service</li>
            <li>Remove reliance on paper-based and manual processes and minimise complexity</li>
            <li>Establish a future-proof digital platform integrated with core corporate systems (Finance, HR, Housing)</li>
        </ul>

        <h3>Core Outcomes</h3>
        <ul>
            <li>Mobile working capability for operatives (jobs, inspections, voids)</li>
            <li>Replacement of Servitor and related systems (scheduling, stock and job costing)</li>
            <li>Integrated ecosystem (NEC Housing, iTrent, Integra)</li>
            <li>Improved planning, scheduling, stock control and reporting</li>
        </ul>

        <h3>Key Benefits</h3>
        <ul>
            <li>Faster response times and improved customer satisfaction</li>
            <li>Real-time visibility of repairs and KPIs</li>
            <li>Improved financial and stock control</li>
            <li>Reduced emissions through smarter scheduling</li>
            <li>Enhanced workforce safety and productivity</li>
        </ul>

        <h3>Delivery Approach</h3>
        <p>Phased delivery: phasing around service design, then solution design and integrations. Staging of work elements instead of project phasing.</p>
        <ul>
            <li><strong>Phase 1:</strong> Procurement, engagement with supplier and stakeholders; and service redesign scoping – as is and to be</li>
            <li><strong>Phase 2:</strong> Solution Design Build and Implementation – including Integrations + expanded functionality</li>
            <li><strong>Phase 3:</strong> Full system replacement go live and retirement of legacy systems</li>
        </ul>
        <p>STOP date for Servitor may be the guide to what goes live when – core service minimum. Understanding of risks and repercussions.</p>

        <h3>Key Risks</h3>
        <ul>
            <li>Data migration complexity</li>
            <li>Staff adoption and change resistance</li>
            <li>Tight alignment with Servitor contract expiry</li>
            <li>Integration complexity across corporate systems</li>
            <li>Security of mobile solutions</li>
        </ul>

    </div>
</div>

<!-- ── Doc 2: Strategic Priorities ───────────────────────────────────── -->
<div class="doc-block" id="strategic-priorities">
    <div class="doc-block-header">
        <div>
            <h2>Strategic Priorities Statement</h2>
            <p class="doc-subtitle">Draft statement — for issue by the Project Owner (Housing Services Manager, Strategic Change, Planning &amp; Support)</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="copyDoc('doc2')">Copy</button>
    </div>
    <div class="doc-body" id="doc2">

        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r);
                    padding:0.6rem 0.85rem;margin-bottom:1rem;font-size:0.8rem;color:var(--muted);">
            <strong style="color:var(--text);">Note:</strong>
            This is a suggested formal steering statement drafted for the Project Owner
            (Housing Services Manager, Strategic Change, Planning &amp; Support) to issue.
            It is written in the first person for their use.
        </div>

        <p>As Project Owner, my priority is to ensure this programme delivers sustainable service transformation rather than system replacement alone. The project will be guided by the following strategic priorities:</p>

        <div style="margin-top:1rem;">

            <div class="priority-item">
                <div class="priority-num">1</div>
                <div>
                    <strong>Service Transformation First, Technology Second</strong>
                    <p style="margin:0.25rem 0 0;">We will prioritise end-to-end service redesign, ensuring processes are streamlined before digitisation. Technology must enable better outcomes, not replicate legacy inefficiencies.</p>
                </div>
            </div>

            <div class="priority-item">
                <div class="priority-num">2</div>
                <div>
                    <strong>Deliver a Seamless Digital Ecosystem</strong>
                    <p style="margin:0.25rem 0 0;">The solution must integrate effectively with NEC Housing, Finance (Integra), and HR (iTrent) to provide a single version of the truth across repairs, workforce and financial data.</p>
                </div>
            </div>

            <div class="priority-item">
                <div class="priority-num">3</div>
                <div>
                    <strong>User-Centred Design and Adoption</strong>
                    <p style="margin:0.25rem 0 0;">Success depends on workforce adoption. We will:</p>
                    <ul style="margin:0.2rem 0 0 1rem;padding:0;">
                        <li>Co-design solutions with operatives and supervisors</li>
                        <li>Invest in training and change management</li>
                        <li>Ensure solutions genuinely improve frontline productivity</li>
                    </ul>
                </div>
            </div>

            <div class="priority-item">
                <div class="priority-num">4</div>
                <div>
                    <strong>Data-Led Decision Making</strong>
                    <p style="margin:0.25rem 0 0;">We will establish real-time reporting, KPI visibility and business intelligence to support proactive service management and continuous improvement.</p>
                </div>
            </div>

            <div class="priority-item">
                <div class="priority-num">5</div>
                <div>
                    <strong>Risk-Based Delivery of Servitor Exit</strong>
                    <p style="margin:0.25rem 0 0;">The replacement of Servitor is a critical driver, and we will:</p>
                    <ul style="margin:0.2rem 0 0 1rem;padding:0;">
                        <li>Maintain a clear migration and transition strategy</li>
                        <li>Consider controlled parallel running if required</li>
                        <li>Avoid compromising delivery quality for artificial deadlines</li>
                    </ul>
                </div>
            </div>

            <div class="priority-item">
                <div class="priority-num">6</div>
                <div>
                    <strong>Security, Compliance and Governance by Design</strong>
                    <p style="margin:0.25rem 0 0;">We will embed:</p>
                    <ul style="margin:0.2rem 0 0 1rem;padding:0;">
                        <li>Data protection and security standards from inception</li>
                        <li>Clear information governance and audit trails</li>
                        <li>Compliance with DPIA and regulatory requirements</li>
                    </ul>
                </div>
            </div>

            <div class="priority-item">
                <div class="priority-num">7</div>
                <div>
                    <strong>Financial Sustainability and Value for Money</strong>
                    <p style="margin:0.25rem 0 0;">We will ensure:</p>
                    <ul style="margin:0.2rem 0 0 1rem;padding:0;">
                        <li>Whole-life cost visibility</li>
                        <li>Clear benefits realisation tracking</li>
                        <li>Procurement of a scalable, future-proof solution</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── Doc 3: Governance Structure ───────────────────────────────────── -->
<div class="doc-block" id="governance-structure">
    <div class="doc-block-header">
        <div>
            <h2>Project Governance Structure</h2>
            <p class="doc-subtitle">Suggested programme and project governance model</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="copyDoc('doc3')">Copy</button>
    </div>
    <div class="doc-body" id="doc3">

        <h3>Programme / Project Governance Model</h3>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <span class="tier-badge">Tier 1</span>
                Project Board
                <span style="font-size:0.78rem;font-weight:400;color:var(--muted);margin-left:auto;">Primary Governance Body</span>
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Chaired by Project Owner (SRO)</li>
                    <li>Accountable for delivery, scope, budget and risk</li>
                    <li>Meets monthly minimum</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <span class="tier-badge">Tier 2</span>
                Programme Board
                <span style="font-size:0.78rem;font-weight:400;color:var(--muted);margin-left:auto;">Escalation &amp; Assurance</span>
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Business Systems &amp; Processes Programme Board</li>
                    <li>Provides oversight, prioritisation and alignment</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <span class="tier-badge">Tier 3</span>
                Design Authority
                <span style="font-size:0.78rem;font-weight:400;color:var(--muted);margin-left:auto;">Technical &amp; Architecture</span>
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Ensures alignment with enterprise architecture, security and integration standards</li>
                    <li>Likely minimum — cloud-based and built into contract</li>
                    <li>Impacts on linked systems and required changes (Product Owner; ICT integration team in liaison with suppliers)</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <span class="tier-badge">Tier 4</span>
                Operational Steering Group
                <span style="font-size:0.78rem;font-weight:400;color:var(--muted);margin-left:auto;">Service Design &amp; Adoption</span>
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Focus on service design, business readiness and adoption</li>
                    <li>Senior level — refine outcomes and expectations</li>
                    <li>Multi-service SMEs for different elements</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <span class="tier-badge">Tier 5</span>
                Project Delivery Team
                <span style="font-size:0.78rem;font-weight:400;color:var(--muted);margin-left:auto;">Day-to-day delivery</span>
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Day-to-day delivery and implementation</li>
                </ul>
            </div>
        </div>

        <h3>Roles and Responsibilities</h3>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="user" style="width:13px;height:13px;color:var(--muted);"></i>
                Project Owner / SRO
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Overall accountability for programme success</li>
                    <li>Strategic decision-making and escalation</li>
                    <li>Chairing Project Board</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="building-2" style="width:13px;height:13px;color:var(--muted);"></i>
                Business Owner — Housing Repairs
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Own service design and operational requirements</li>
                    <li>Lead business change and adoption</li>
                    <li>Provide subject matter expertise</li>
                    <li>Ensure benefits realisation within the service</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="monitor" style="width:13px;height:13px;color:var(--muted);"></i>
                ICT &amp; Digital
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Provide technical architecture and infrastructure</li>
                    <li>Ensure cybersecurity and compliance</li>
                    <li>Support mobile device deployment and integration</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="landmark" style="width:13px;height:13px;color:var(--muted);"></i>
                Finance — Integra
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Support financial integration and reporting</li>
                    <li>Ensure appropriate financial controls are embedded</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="users" style="width:13px;height:13px;color:var(--muted);"></i>
                HR — iTrent
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Support workforce integration and organisational design</li>
                    <li>Enable workforce planning and role alignment</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="git-merge" style="width:13px;height:13px;color:var(--muted);"></i>
                Business Systems &amp; Processes
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Lead systems integration and interface development</li>
                    <li>Support data, reporting and process alignment</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="file-check" style="width:13px;height:13px;color:var(--muted);"></i>
                Procurement
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Ensure compliant and robust supplier selection</li>
                    <li>Support contract negotiation and supplier management</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">
                <i data-lucide="truck" style="width:13px;height:13px;color:var(--muted);"></i>
                Suppliers / Delivery Partners
            </div>
            <div class="gov-tier-body">
                <ul>
                    <li>Deliver agreed solution and implementation services</li>
                    <li>Provide technical expertise and support</li>
                    <li>Meet contractual performance standards</li>
                </ul>
            </div>
        </div>

        <h3>Stakeholder Expectations</h3>

        <div class="gov-tier">
            <div class="gov-tier-head">All Stakeholders</div>
            <div class="gov-tier-body">
                <ul>
                    <li>Engage proactively in design and decision-making</li>
                    <li>Support change within their service areas</li>
                    <li>Escalate risks and issues early</li>
                </ul>
            </div>
        </div>

        <div class="gov-tier">
            <div class="gov-tier-head">Corporate Services — ICT, Finance, HR, BS&amp;P</div>
            <div class="gov-tier-body">
                <ul>
                    <li>Support integration and technical delivery</li>
                    <li>Ensure compliance with corporate standards</li>
                    <li>Provide specialist expertise</li>
                </ul>
            </div>
        </div>

        <h3>Governance and Decision Making</h3>
        <ul>
            <li>Strategic decisions will be made by the Project Board</li>
            <li>Escalation will be routed through the Programme Board</li>
            <li>Working groups will support operational and technical delivery</li>
        </ul>

        <h3>Principles of Working</h3>
        <ul>
            <li><strong>Collaboration:</strong> Work openly across services and disciplines</li>
            <li><strong>Transparency:</strong> Share information, risks and issues promptly</li>
            <li><strong>User focus:</strong> Prioritise frontline usability and customer outcomes</li>
            <li><strong>Accountability:</strong> Take ownership of responsibilities and deliverables</li>
            <li><strong>Compliance:</strong> Adhere to data protection, security and governance requirements</li>
        </ul>

        <h3>Resource Commitment</h3>
        <p>All stakeholders agree to:</p>
        <ul>
            <li>Allocate appropriate resources to support delivery</li>
            <li>Participate in design, testing and implementation activities</li>
            <li>Balance programme commitments with business-as-usual pressures</li>
        </ul>

        <h3>Risk and Issue Management</h3>
        <p>Stakeholders will:</p>
        <ul>
            <li>Actively identify and manage risks within their areas</li>
            <li>Escalate issues in a timely manner</li>
            <li>Support mitigation planning and delivery</li>
        </ul>

    </div>
</div>

</div><!-- /.doc-section -->

<script>
function copyDoc(id) {
    const el = document.getElementById(id);
    if (!el) return;

    // Build plain text from the element
    const lines = [];
    el.querySelectorAll('h3, p, li, strong').forEach(node => {
        const tag  = node.tagName.toLowerCase();
        const text = node.innerText.trim();
        if (!text) return;
        if (tag === 'h3')     lines.push('\n' + text.toUpperCase() + '\n' + '─'.repeat(text.length));
        else if (tag === 'li') lines.push('• ' + text);
        else if (tag === 'p')  lines.push(text);
    });

    const plain = lines.join('\n').replace(/\n{3,}/g, '\n\n').trim();

    navigator.clipboard.writeText(plain).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = 'Copied';
        btn.disabled = true;
        setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 2000);
    });
}
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
<?php
render_layout('Boards & Groups', ob_get_clean() ?: '');
