<?php
/**
 * Copyright (c) Enalean, 2017. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\Git;

use ForgeConfig;
use Git;
use GitRepository;
use Logger;
use SimpleXMLElement;
use Project;
use GitPermissionsManager;
use System_Command;
use Tuleap\GitBundle;
use Tuleap\Project\XML\Export\ArchiveInterface;
use UGroupManager;
use ProjectUGroup;
use GitRepositoryFactory;

class GitXmlExporter
{
    const EXPORT_FOLDER = "export";

    /**
     * @var Project
     */
    private $project;

    /**
     * @var GitPermissionsManager
     */
    private $permission_manager;

    /**
     * @var UGroupManager
     */
    private $ugroup_manager;

    /**
     * @var GitRepositoryFactory
     */
    private $repository_factory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var System_Command
     */
    private $command;
    /**
     * @var GitBundle
     */
    private $git_bundle;

    public function __construct(
        Project $project,
        GitPermissionsManager $permission_manager,
        UGroupManager $ugroup_manager,
        GitRepositoryFactory $repository_factory,
        Logger $logger,
        System_Command $command,
        GitBundle $git_bundle
    ) {
        $this->project            = $project;
        $this->permission_manager = $permission_manager;
        $this->ugroup_manager     = $ugroup_manager;
        $this->repository_factory = $repository_factory;
        $this->logger             = $logger;
        $this->command            = $command;
        $this->git_bundle         = $git_bundle;
    }

    public function exportToXml(SimpleXMLElement $xml_content, ArchiveInterface $archive, $temporary_dump_path_on_filesystem)
    {
        $root_node = $xml_content->addChild("git");
        $this->exportGitAdministrators($root_node);

        $this->exportGitRepositories($root_node, $temporary_dump_path_on_filesystem, $archive);
    }

    private function exportGitAdministrators(SimpleXMLElement $xml_content)
    {
        $this->logger->info('Export git administrators');
        $root_node     = $xml_content->addChild("ugroups-admin");
        $admin_ugroups = $this->permission_manager->getCurrentGitAdminUgroups($this->project->getId());

        foreach ($admin_ugroups as $ugroup) {
            $root_node->addChild("ugroup", $this->getLabelForUgroup($ugroup));
        }
    }

    private function getLabelForUgroup($ugroup)
    {
        if ($ugroup === ProjectUGroup::PROJECT_MEMBERS) {
            return $GLOBALS['Language']->getText('project_ugroup', 'ugroup_project_members_name_key');
        }

        if ($ugroup === ProjectUGroup::PROJECT_ADMIN) {
            return $GLOBALS['Language']->getText('project_ugroup', 'ugroup_project_admins_name_key');
        }

        return $this->ugroup_manager->getUGroup($this->project, $ugroup)->getTranslatedName();
    }

    private function exportGitRepositories(
        SimpleXMLElement $xml_content,
        $temporary_dump_path_on_filesystem,
        ArchiveInterface $archive
    ) {
        $this->logger->info('Export git repositories');
        $repositories = $this->repository_factory->getAllRepositories($this->project);

        $archive->addEmptyDir('export');

        foreach ($repositories as $repository) {
            if ($repository->getParent()) {
                continue;
            }

            $root_node = $xml_content->addChild("repository");
            $root_node->addAttribute("name", $repository->getName());
            $root_node->addAttribute("description", $repository->getDescription());

            $bundle_path = "";
            if ($repository->isInitialized()) {
                $bundle_path = self::EXPORT_FOLDER . DIRECTORY_SEPARATOR . $repository->getName() . '.bundle';
            }

            $root_node->addAttribute(
                "bundle-path",
                $bundle_path
            );

            $this->bundleRepository($repository, $temporary_dump_path_on_filesystem, $archive);

            $this->exportGitRepositoryPermissions($repository, $root_node);
        }
    }

    private function bundleRepository(
        GitRepository $repository,
        $temporary_dump_path_on_filesystem,
        ArchiveInterface $archive
    ) {
        $this->logger->info('Create git bundle for repository ' . $repository->getName());

        $this->git_bundle->dumpRepository($repository, $archive, $temporary_dump_path_on_filesystem);
    }

    private function exportGitRepositoryPermissions(GitRepository $repository, SimpleXMLElement $xml_content)
    {
        $this->logger->info('Export repository permissions');
        $default_permissions = $this->permission_manager->getRepositoryGlobalPermissions($repository);

        $read_node = $xml_content->addChild("read");
        if (isset($default_permissions[Git::PERM_READ])) {
            $this->exportPermission($read_node, $default_permissions[Git::PERM_READ]);
        }

        if (isset($default_permissions[Git::PERM_WRITE])) {
            $write_node = $xml_content->addChild("write");
            $this->exportPermission($write_node, $default_permissions[Git::PERM_WRITE]);
        }

        if (isset($default_permissions[Git::PERM_WPLUS])) {
            $wplus_node = $xml_content->addChild("wplus");
            $this->exportPermission($wplus_node, $default_permissions[Git::PERM_WPLUS]);
        }
    }

    private function exportPermission(SimpleXMLElement $xml_content, $permissions)
    {
        foreach ($permissions as $permission) {
            $xml_content->addChild("ugroup", $this->getLabelForUgroup($permission));
        }
    }
}
