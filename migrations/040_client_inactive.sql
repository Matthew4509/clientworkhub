
UPDATE client SET status = 'inactive' WHERE status = 'archived';

UPDATE reminder r
  JOIN client c ON c.id = r.client_id
   SET r.status = 'cancelled', r.updated_at = NOW()
 WHERE c.status = 'inactive'
   AND r.status IN ('pending', 'snoozed');

UPDATE reminder r
  JOIN job j    ON j.id = r.job_id
  JOIN client c ON c.id = j.client_id
   SET r.status = 'cancelled', r.updated_at = NOW()
 WHERE c.status = 'inactive'
   AND r.status IN ('pending', 'snoozed');
