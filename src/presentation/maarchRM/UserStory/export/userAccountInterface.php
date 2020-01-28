<?php

namespace presentation\maarchRM\UserStory\Export;

interface userAccountInterface
{
    /**
     * Get user account infos
     *
     * @uses auth/userAccount/readExport
     *
     * @return importExport/Export/listCsv
     *
     */
    public function readExportUseraccounts($limit = null);

    /**
     * Get user account infos
     *
     * @uses auth/userAccount/readExport
     *
     * @return importExport/Export/export
     *
     */
    public function readExportallUseraccounts($limit = null);
}
