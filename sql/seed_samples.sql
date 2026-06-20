DELETE FROM as_is_documents WHERE slug IN ('sample-customer-first', 'sample-purchase-to-pay', 'sample-repair-quick');

INSERT INTO systems (name, description) VALUES
    ('Liberty Converse',     'Primary telephony and call-logging platform'),
    ('NEC Housing',          'Job creation and repair management system'),
    ('DRS',                  'Dynamic Resource Scheduler for appointment booking'),
    ('GOSS',                 'Web form system used for escalations when DRS access is unavailable'),
    ('e-Procurement Portal', 'Online system for raising and approving purchase requests and orders'),
    ('Accounts Payable',     'Finance system for processing invoices and payment runs')
ON DUPLICATE KEY UPDATE description = VALUES(description);

SET @sys_lc  = (SELECT id FROM systems WHERE name = 'Liberty Converse'),
    @sys_nec = (SELECT id FROM systems WHERE name = 'NEC Housing'),
    @sys_drs = (SELECT id FROM systems WHERE name = 'DRS'),
    @sys_goss= (SELECT id FROM systems WHERE name = 'GOSS'),
    @sys_ep  = (SELECT id FROM systems WHERE name = 'e-Procurement Portal'),
    @sys_ap  = (SELECT id FROM systems WHERE name = 'Accounts Payable');


INSERT INTO as_is_documents (title, slug, description, status, owner, department, captured_date, version) VALUES (
    'Customer First — Housing Repairs',
    'sample-customer-first',
    'Telephone call-handling process for tenant repair requests. Covers new jobs, job updates, reschedules and escalations to the Technical Officer team.',
    'published', 'Housing Services Team', 'Housing', '2023-05-17', 'v1.2'
);
SET @doc1 = LAST_INSERT_ID();

INSERT INTO lanes (as_is_id, name, sort_order, color) VALUES
    (@doc1, 'Tenant',            1, '#fff3e0'),
    (@doc1, 'Customer First',    2, '#e8f5e9'),
    (@doc1, 'Technical Officer', 3, '#e3f2fd');
SET @l1_tenant = (SELECT id FROM lanes WHERE as_is_id = @doc1 AND name = 'Tenant'),
    @l1_cf     = (SELECT id FROM lanes WHERE as_is_id = @doc1 AND name = 'Customer First'),
    @l1_to     = (SELECT id FROM lanes WHERE as_is_id = @doc1 AND name = 'Technical Officer');

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type, action_type) VALUES
    (@doc1, @l1_tenant, 1,  'Tenant contacts service',              'Tenant calls about a repair, job update, reschedule or missed appointment.',                     'start',    'phone'),
    (@doc1, @l1_cf,     2,  'Call received',                        'Customer First agent answers the call on Liberty Converse.',                                       'task',     'phone'),
    (@doc1, @l1_cf,     3,  'Log caller details',                   'Record tenant name, address and contact number in Liberty Converse.',                              'task',     'data-entry'),
    (@doc1, @l1_cf,     4,  'What type of request?',                'Is this a new repair, job update, reschedule, cancellation or missed appointment?',               'decision', 'general'),
    (@doc1, @l1_cf,     5,  'Check property access',                'Confirm access arrangements and any vulnerabilities flagged on the property record.',              'task',     'check'),
    (@doc1, @l1_cf,     6,  'Create repair job on NEC',             'Raise a new repair, assign the trade and record the full repair description in NEC Housing.',     'task',     'data-entry'),
    (@doc1, @l1_cf,     7,  'Book appointment on DRS',              'Find an available slot in DRS and confirm the booking.',                                           'task',     'data-entry'),
    (@doc1, @l1_cf,     8,  'Confirm appointment with tenant',      'Read the date and time back to the tenant. Advise of any access requirements.',                    'task',     'phone'),
    (@doc1, @l1_cf,     9,  'Send appointment confirmation',        'Send SMS or email confirmation to the tenant via Liberty Converse.',                               'task',     'email'),
    (@doc1, @l1_cf,     10, 'Close call',                           'Complete call notes, set the outcome code and close the call on Liberty Converse.',               'end',      'general'),
    (@doc1, @l1_cf,     11, 'Locate existing job on NEC',           'Search NEC Housing for the job reference. Verify correct property and trade.',                    'task',     'data-entry'),
    (@doc1, @l1_cf,     12, 'Apply update or reschedule',           'Make the requested change to the job or appointment in NEC Housing or DRS.',                      'task',     'data-entry'),
    (@doc1, @l1_cf,     13, 'Confirm change with tenant',           'Read the updated details back to the tenant and check they are satisfied.',                        'task',     'phone'),
    (@doc1, @l1_cf,     14, 'Complete GOSS escalation form',        'No DRS access: fill in the GOSS web form with full repair and contact details.',                  'task',     'document'),
    (@doc1, @l1_cf,     15, 'Submit GOSS form to TO mailbox',       'Submit the form — it routes automatically to the Technical Officer shared mailbox.',              'task',     'email'),
    (@doc1, @l1_cf,     16, 'Advise tenant of callback',            'Tell the tenant that a Technical Officer will call them to confirm the appointment.',             'task',     'phone'),
    (@doc1, @l1_to,     17, 'Pick up GOSS form from mailbox',       'Technical Officer checks the shared mailbox and opens the submitted GOSS form.',                  'task',     'email'),
    (@doc1, @l1_to,     18, 'Create job on NEC',                    'Raise the repair job on NEC Housing using the details from the GOSS form.',                       'task',     'data-entry'),
    (@doc1, @l1_to,     19, 'Book appointment on DRS',              'Book the appointment slot in DRS.',                                                               'task',     'data-entry'),
    (@doc1, @l1_to,     20, 'Contact tenant to confirm appointment','Call the tenant to confirm the appointment date and time.',                                         'task',     'phone'),
    (@doc1, @l1_to,     21, 'Notify Customer First team',           'Email Customer First to confirm the job has been raised and the appointment booked.',             'task',     'email');

SET @d1s1  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 1),
    @d1s2  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 2),
    @d1s3  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 3),
    @d1s4  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 4),
    @d1s5  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 5),
    @d1s6  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 6),
    @d1s7  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 7),
    @d1s8  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 8),
    @d1s9  = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 9),
    @d1s10 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 10),
    @d1s11 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 11),
    @d1s12 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 12),
    @d1s13 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 13),
    @d1s14 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 14),
    @d1s15 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 15),
    @d1s16 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 16),
    @d1s17 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 17),
    @d1s18 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 18),
    @d1s19 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 19),
    @d1s20 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 20),
    @d1s21 = (SELECT id FROM steps WHERE as_is_id = @doc1 AND step_number = 21);

INSERT INTO step_systems (step_id, system_id) VALUES
    (@d1s2,  @sys_lc), (@d1s3,  @sys_lc), (@d1s6,  @sys_nec), (@d1s7,  @sys_drs),
    (@d1s9,  @sys_lc), (@d1s11, @sys_nec), (@d1s12, @sys_nec), (@d1s12, @sys_drs),
    (@d1s14, @sys_goss), (@d1s15, @sys_goss), (@d1s18, @sys_nec), (@d1s19, @sys_drs);

INSERT INTO step_connections (from_step_id, to_step_id, label) VALUES
    (@d1s1,  @d1s2,  NULL), (@d1s2,  @d1s3,  NULL), (@d1s3,  @d1s4,  NULL),
    (@d1s4,  @d1s5,  'New repair'), (@d1s4,  @d1s11, 'Update / reschedule'), (@d1s4,  @d1s14, 'No DRS access'),
    (@d1s5,  @d1s6,  NULL), (@d1s6,  @d1s7,  NULL), (@d1s7,  @d1s8,  NULL),
    (@d1s8,  @d1s9,  NULL), (@d1s9,  @d1s10, NULL),
    (@d1s11, @d1s12, NULL), (@d1s12, @d1s13, NULL), (@d1s13, @d1s10, NULL),
    (@d1s14, @d1s15, NULL), (@d1s15, @d1s16, NULL), (@d1s16, @d1s10, NULL),
    (@d1s15, @d1s17, NULL), (@d1s17, @d1s18, NULL), (@d1s18, @d1s19, NULL),
    (@d1s19, @d1s20, NULL), (@d1s20, @d1s21, NULL);


INSERT INTO as_is_documents (title, slug, description, status, owner, department, captured_date, version) VALUES (
    'Purchase to Pay — Procurement',
    'sample-purchase-to-pay',
    'End-to-end procurement process from identifying a purchasing need through to supplier payment. Includes approval gates, delivery checking and invoice matching.',
    'published', 'Procurement Services', 'Finance and Resources', '2024-01-15', 'v2.1'
);
SET @doc2 = LAST_INSERT_ID();

INSERT INTO lanes (as_is_id, name, sort_order, color) VALUES
    (@doc2, 'Budget Holder', 1, '#fce4ec'),
    (@doc2, 'Procurement',   2, '#e8eaf6'),
    (@doc2, 'Finance',       3, '#e0f2f1'),
    (@doc2, 'Supplier',      4, '#fff8e1');
SET @l2_bh   = (SELECT id FROM lanes WHERE as_is_id = @doc2 AND name = 'Budget Holder'),
    @l2_proc = (SELECT id FROM lanes WHERE as_is_id = @doc2 AND name = 'Procurement'),
    @l2_fin  = (SELECT id FROM lanes WHERE as_is_id = @doc2 AND name = 'Finance'),
    @l2_sup  = (SELECT id FROM lanes WHERE as_is_id = @doc2 AND name = 'Supplier');

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type, action_type) VALUES
    (@doc2, @l2_bh,   1,  'Identify purchasing need',               'Budget holder identifies a requirement for goods or services not available through existing contracts.', 'start',    'check'),
    (@doc2, @l2_bh,   2,  'Raise purchase request',                 'Complete a purchase request in the e-Procurement Portal including cost centre and justification.',        'task',     'document'),
    (@doc2, @l2_bh,   3,  'Submit for approval',                    'Request is submitted via the portal and routed automatically to the Procurement team for review.',        'task',     'email'),
    (@doc2, @l2_proc, 4,  'Review purchase request',                'Procurement officer reviews the request for completeness, policy compliance and available budget.',       'task',     'check'),
    (@doc2, @l2_proc, 5,  'Approve or reject?',                     'Is the purchase request within policy and budget?',                                                       'decision', 'meeting'),
    (@doc2, @l2_proc, 6,  'Notify requester of rejection',          'Email the budget holder explaining the reason for rejection and what changes are needed.',                'task',     'email'),
    (@doc2, @l2_bh,   7,  'Amend and resubmit request',             'Budget holder revises the request based on feedback and resubmits through the portal.',                  'task',     'document'),
    (@doc2, @l2_proc, 8,  'Raise purchase order',                   'Procurement raises a formal purchase order in the e-Procurement Portal and assigns a PO number.',        'task',     'document'),
    (@doc2, @l2_proc, 9,  'Send PO to supplier',                    'PO is emailed or transmitted to the supplier via the portal.',                                            'task',     'email'),
    (@doc2, @l2_sup,  10, 'Acknowledge PO receipt',                 'Supplier acknowledges receipt of the purchase order and confirms they can fulfil it.',                    'task',     'email'),
    (@doc2, @l2_sup,  11, 'Goods or services delivered',            'Supplier delivers the goods or performs the services. Delivery may take days or weeks.',                  'task',     'wait'),
    (@doc2, @l2_bh,   12, 'Receive and inspect delivery',           'Budget holder receives the goods or confirms services were performed and checks against the PO.',         'task',     'check'),
    (@doc2, @l2_bh,   13, 'Delivery satisfactory?',                 'Do the goods or services match the order in full, at the right quality and condition?',                   'decision', 'check'),
    (@doc2, @l2_bh,   14, 'Raise delivery dispute',                 'Budget holder escalates the issue to Procurement with details of the shortfall or defect.',               'task',     'escalation'),
    (@doc2, @l2_fin,  15, 'Invoice received from supplier',         'Finance team receives a supplier invoice and logs it in Accounts Payable.',                               'task',     'document'),
    (@doc2, @l2_fin,  16, 'Three-way match: PO, delivery, invoice', 'Finance checks that the invoice value and goods match the PO and the delivery confirmation.',            'task',     'check'),
    (@doc2, @l2_fin,  17, 'Approved for payment?',                  'Does the invoice match the PO and delivery record?',                                                     'decision', 'meeting'),
    (@doc2, @l2_fin,  18, 'Query invoice with supplier',            'Finance contacts the supplier to resolve the discrepancy before proceeding.',                             'task',     'email'),
    (@doc2, @l2_fin,  19, 'Authorise and process payment',          'Finance authorises the payment run and processes the payment in Accounts Payable.',                       'task',     'data-entry'),
    (@doc2, @l2_fin,  20, 'Payment complete',                       'Payment is made to the supplier. Budget holder and Procurement are notified.',                            'end',      'general');

SET @d2s1  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 1),
    @d2s2  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 2),
    @d2s3  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 3),
    @d2s4  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 4),
    @d2s5  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 5),
    @d2s6  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 6),
    @d2s7  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 7),
    @d2s8  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 8),
    @d2s9  = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 9),
    @d2s10 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 10),
    @d2s11 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 11),
    @d2s12 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 12),
    @d2s13 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 13),
    @d2s14 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 14),
    @d2s15 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 15),
    @d2s16 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 16),
    @d2s17 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 17),
    @d2s18 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 18),
    @d2s19 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 19),
    @d2s20 = (SELECT id FROM steps WHERE as_is_id = @doc2 AND step_number = 20);

INSERT INTO step_systems (step_id, system_id) VALUES
    (@d2s2,  @sys_ep), (@d2s3,  @sys_ep), (@d2s4,  @sys_ep),
    (@d2s7,  @sys_ep), (@d2s8,  @sys_ep), (@d2s9,  @sys_ep),
    (@d2s15, @sys_ap), (@d2s16, @sys_ap), (@d2s19, @sys_ap);

INSERT INTO step_connections (from_step_id, to_step_id, label) VALUES
    (@d2s1,  @d2s2,  NULL), (@d2s2,  @d2s3,  NULL), (@d2s3,  @d2s4,  NULL), (@d2s4,  @d2s5,  NULL),
    (@d2s5,  @d2s6,  'Rejected'), (@d2s5,  @d2s8,  'Approved'),
    (@d2s6,  @d2s7,  NULL), (@d2s7,  @d2s3,  'Resubmit'),
    (@d2s8,  @d2s9,  NULL), (@d2s9,  @d2s10, NULL), (@d2s10, @d2s11, NULL),
    (@d2s11, @d2s12, NULL), (@d2s12, @d2s13, NULL),
    (@d2s13, @d2s14, 'Issue found'), (@d2s13, @d2s15, 'Accepted'),
    (@d2s14, @d2s11, 'Re-deliver'),
    (@d2s15, @d2s16, NULL), (@d2s16, @d2s17, NULL),
    (@d2s17, @d2s18, 'Query'), (@d2s17, @d2s19, 'Approved'),
    (@d2s18, @d2s15, 'Revised invoice'),
    (@d2s19, @d2s20, NULL);


-- ── Sample 3: Housing Repair — Quick View ────────────────────────────────────
-- Short illustrative example (7 steps, 3 lanes) designed to be fully readable
-- at default zoom. Mirrors the style of the home page illustration.

INSERT INTO as_is_documents (title, slug, description, status, owner, department, captured_date, version) VALUES (
    'Housing Repair — Quick View',
    'sample-repair-quick',
    'A short illustrative example showing how a repair request flows from the tenant through housing officers to the trade team. Designed to be readable at a glance.',
    'published', 'Housing Services', 'Housing', '2024-03-01', 'v1.0'
);
SET @doc3 = LAST_INSERT_ID();

INSERT INTO lanes (as_is_id, name, sort_order, color) VALUES
    (@doc3, 'Tenant',         1, '#fff3e0'),
    (@doc3, 'Housing Officer',2, '#e8f5e9'),
    (@doc3, 'Trade Team',     3, '#e3f2fd');
SET @l3_t  = (SELECT id FROM lanes WHERE as_is_id = @doc3 AND name = 'Tenant'),
    @l3_ho = (SELECT id FROM lanes WHERE as_is_id = @doc3 AND name = 'Housing Officer'),
    @l3_tr = (SELECT id FROM lanes WHERE as_is_id = @doc3 AND name = 'Trade Team');

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type, action_type) VALUES
    (@doc3, @l3_t,  1, 'Report repair',      'Tenant calls in to report a repair needed at their property.',                            'start',    'phone'),
    (@doc3, @l3_ho, 2, 'Log request',        'Log caller details and repair description. Check property notes for vulnerabilities.',    'task',     'data-entry'),
    (@doc3, @l3_ho, 3, 'Assess job',         'Review repair type and urgency against the housing repairs policy.',                     'task',     'check'),
    (@doc3, @l3_ho, 4, 'Priority?',          'Is this an emergency repair or a routine appointment job?',                             'decision', 'general'),
    (@doc3, @l3_ho, 5, 'Book appointment',   'Find available slot and book a standard appointment within the target timescale.',       'task',     'data-entry'),
    (@doc3, @l3_ho, 6, 'Raise urgent job',   'Flag as emergency, notify on-call trade supervisor, and log in the system.',            'task',     'escalation'),
    (@doc3, @l3_tr, 7, 'Complete repair',    'Trade operative attends, carries out the repair, and records the outcome on site.',     'end',      'visit');

SET @d3s1 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 1),
    @d3s2 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 2),
    @d3s3 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 3),
    @d3s4 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 4),
    @d3s5 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 5),
    @d3s6 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 6),
    @d3s7 = (SELECT id FROM steps WHERE as_is_id = @doc3 AND step_number = 7);

INSERT INTO step_connections (from_step_id, to_step_id, label) VALUES
    (@d3s1, @d3s2, NULL),
    (@d3s2, @d3s3, NULL),
    (@d3s3, @d3s4, NULL),
    (@d3s4, @d3s5, 'Routine'),
    (@d3s4, @d3s6, 'Emergency'),
    (@d3s5, @d3s7, NULL),
    (@d3s6, @d3s7, NULL);
