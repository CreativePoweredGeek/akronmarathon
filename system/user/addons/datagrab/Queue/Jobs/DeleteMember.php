<?php

namespace BoldMinded\DataGrab\Queue\Jobs;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ShouldBeUnique;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\ShouldQueue;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Queue\Job as DataGrabJobContract;
use BoldMinded\DataGrab\Service\ContentType\MemberContentType;
use BoldMinded\Queue\Dependency\Illuminate\Contracts\Queue\Job as QueueJobContract;

/**
 * Members are never hard deleted by an import. Any member that wasn't part of
 * the import run is moved into EE's Banned role instead, which preserves their
 * content and audit trail while revoking access.
 */
class DeleteMember extends AbstractJob implements ShouldQueue, ShouldBeUnique
{
    public function fire(DataGrabJobContract|QueueJobContract $job, array $payload = []): bool
    {
        $this->job = $job;

        if (!$this->isValidImport($this->getImportId('delete'))) {
            return false;
        }

        if (!isset($payload['entryId']) || !$payload['entryId']) {
            return true;
        }

        $memberId = (int) $payload['entryId'];

        ee('datagrab:Importer')->logger->log('DeleteMember PID: ' . getmypid());

        $member = ee('Model')->get('Member', $memberId)->first();

        if (!$member) {
            $this->job->delete();

            return true;
        }

        // Guard against locking out Super Admins, even if one slipped through
        // the query that built the delete queue.
        if ($member->isSuperAdmin()) {
            ee('datagrab:Importer')->logger->log(
                'Skipping ban for Super Admin member #' . $memberId
            );

            $this->job->delete();

            return true;
        }

        // Anonymizing is EE's GDPR "right to erasure" routine. It bans the member
        // and additionally scrubs username, email, IP, avatars and any custom
        // field not flagged m_field_exclude_from_anon. This is irreversible, so
        // it is opt-in and ban remains the default.
        if (get_bool_from_string($this->settings['config']['anonymize_deleted'] ?? 'no')) {
            if ($member->isAnonymized()) {
                ee('datagrab:Importer')->logger->log(
                    'Member #' . $memberId . ' is already anonymized. Skipping.'
                );

                $this->job->delete();

                return true;
            }

            // anonymize() reaches for ee()->logger and ee()->extensions, which
            // aren't guaranteed to be loaded in a queue worker.
            ee()->load->library('logger');

            try {
                $member->anonymize();

                ee('datagrab:Importer')->logger->log('Anonymizing member #' . $memberId);
            } catch (\Exception $exception) {
                ee('datagrab:Importer')->logger->log(sprintf(
                    'Unable to anonymize member #%d: %s',
                    $memberId,
                    $exception->getMessage()
                ));

                return false;
            }
        } else {
            $member->role_id = MemberContentType::BANNED_ROLE_ID;
            $member->save();

            ee('datagrab:Importer')->logger->log('Banning member #' . $memberId);
        }

        ee('datagrab:Importer')->recordDeletedEntryIds();

        $this->job->delete();

        return true;
    }
}
