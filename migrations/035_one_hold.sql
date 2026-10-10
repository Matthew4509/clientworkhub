
UPDATE job SET status = 'hold', updated_at = NOW()
 WHERE status IN ('waiting', 'paused');
