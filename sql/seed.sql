INSERT INTO as_is_documents (title, slug, description, status)
SELECT 'Customer First', 'customer-first', 'Tenant request handling — opening flow (sample from AS-IS diagram).', 'published'
WHERE NOT EXISTS (
    SELECT 1 FROM as_is_documents WHERE slug = 'customer-first'
);

SET @doc_id = (SELECT id FROM as_is_documents WHERE title = 'Customer First' LIMIT 1);

INSERT INTO lanes (as_is_id, name, sort_order, color)
SELECT @doc_id, 'Tenant', 1, '#fff3e0'
WHERE NOT EXISTS (SELECT 1 FROM lanes WHERE as_is_id = @doc_id AND name = 'Tenant');

INSERT INTO lanes (as_is_id, name, sort_order, color)
SELECT @doc_id, 'Customer First', 2, '#e8f5e9'
WHERE NOT EXISTS (SELECT 1 FROM lanes WHERE as_is_id = @doc_id AND name = 'Customer First');

INSERT INTO lanes (as_is_id, name, sort_order, color)
SELECT @doc_id, 'Technical Officer', 3, '#e3f2fd'
WHERE NOT EXISTS (SELECT 1 FROM lanes WHERE as_is_id = @doc_id AND name = 'Technical Officer');

SET @lane_tenant = (SELECT id FROM lanes WHERE as_is_id = @doc_id AND name = 'Tenant' LIMIT 1);
SET @lane_cf = (SELECT id FROM lanes WHERE as_is_id = @doc_id AND name = 'Customer First' LIMIT 1);
SET @lane_to = (SELECT id FROM lanes WHERE as_is_id = @doc_id AND name = 'Technical Officer' LIMIT 1);

INSERT INTO systems (name, description) VALUES ('Liberty Converse', 'Primary call handling system')
    ON DUPLICATE KEY UPDATE description = VALUES(description);
INSERT INTO systems (name, description) VALUES ('NEC', 'Job creation and job numbers')
    ON DUPLICATE KEY UPDATE description = VALUES(description);
INSERT INTO systems (name, description) VALUES ('DRS', 'Scheduling and job status')
    ON DUPLICATE KEY UPDATE description = VALUES(description);

SET @sys_liberty = (SELECT id FROM systems WHERE name = 'Liberty Converse' LIMIT 1);
SET @sys_nec     = (SELECT id FROM systems WHERE name = 'NEC'              LIMIT 1);
SET @sys_drs     = (SELECT id FROM systems WHERE name = 'DRS'              LIMIT 1);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_tenant, 1, 'Tenant contacts service', 'Tenant initiates contact about a repair or request.', 'start'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 1);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_cf, 2, 'Call received', 'Customer First agent receives the call.', 'task'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 2);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_cf, 3, 'Accept on Liberty Converse', 'Log and accept the call in Liberty Converse.', 'task'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 3);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_cf, 4, 'What type of request?', 'Route to New Job, Update, Reschedule, Cancel, or Missed Appointment.', 'decision'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 4);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_cf, 5, 'New job path', 'Handler has DRS access — create job on NEC and continue.', 'task'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 5);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_cf, 28, 'GOSS form escalation', 'No DRS access — complete GOSS form and send to mailbox.', 'task'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 28);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_to, 44, 'Technical Officer review', 'Technical Officer picks up escalated GOSS form work.', 'task'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 44);

INSERT INTO steps (as_is_id, lane_id, step_number, title, description, step_type)
SELECT @doc_id, @lane_cf, 31, 'Close call', 'Close the call on Liberty and stop.', 'end'
WHERE NOT EXISTS (SELECT 1 FROM steps WHERE as_is_id = @doc_id AND step_number = 31);

SET @s1 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 1);
SET @s2 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 2);
SET @s3 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 3);
SET @s4 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 4);
SET @s5 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 5);
SET @s28 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 28);
SET @s44 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 44);
SET @s31 = (SELECT id FROM steps WHERE as_is_id = @doc_id AND step_number = 31);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s1, @s2, NULL
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s1 AND to_step_id = @s2);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s2, @s3, NULL
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s2 AND to_step_id = @s3);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s3, @s4, NULL
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s3 AND to_step_id = @s4);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s4, @s5, 'New job'
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s4 AND to_step_id = @s5);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s4, @s28, 'No DRS access'
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s4 AND to_step_id = @s28);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s28, @s44, NULL
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s28 AND to_step_id = @s44);

INSERT INTO step_connections (from_step_id, to_step_id, label)
SELECT @s28, @s31, NULL
WHERE NOT EXISTS (SELECT 1 FROM step_connections WHERE from_step_id = @s28 AND to_step_id = @s31);

INSERT INTO step_systems (step_id, system_id)
SELECT @s3, @sys_liberty
WHERE NOT EXISTS (SELECT 1 FROM step_systems WHERE step_id = @s3 AND system_id = @sys_liberty);

INSERT INTO step_systems (step_id, system_id)
SELECT @s5, @sys_nec
WHERE NOT EXISTS (SELECT 1 FROM step_systems WHERE step_id = @s5 AND system_id = @sys_nec);

INSERT INTO step_systems (step_id, system_id)
SELECT @s5, @sys_drs
WHERE NOT EXISTS (SELECT 1 FROM step_systems WHERE step_id = @s5 AND system_id = @sys_drs);
