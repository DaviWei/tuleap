<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace Tuleap\Git\Permissions;

use GitRepository;
use UGroupManager;
use Codendi_Request;
use PermissionsNormalizer;
use PermissionsNormalizerOverrideCollection;
use Project;
use PermissionsManager;
use Git;
use Feedback;

class FineGrainedPermissionFactory
{

    /**
     * @var FineGrainedPermissionSorter
     */
    private $sorter;

    /**
     * @var PatternValidator
     */
    private $validator;

    const ADD_BRANCH_PREFIX  = 'add-branch';
    const ADD_TAG_PREFIX     = 'add-tag';
    const EDIT_BRANCH_PREFIX = 'edit-branch';
    const EDIT_TAG_PREFIX    = 'edit-tag';

    /**
     * @var PermissionsManager
     */
    private $permissions_manager;

    /**
     * @var PermissionsNormalizer
     */
    private $normalizer;

    /**
     * @var UGroupManager
     */
    private $ugroup_manager;

    /**
     * @var FineGrainedDao
     */
    private $dao;

    public function __construct(
        FineGrainedDao $dao,
        UGroupManager $ugroup_manager,
        PermissionsNormalizer $normalizer,
        PermissionsManager $permissions_manager,
        PatternValidator $validator,
        FineGrainedPermissionSorter $sorter
    ) {
        $this->dao                 = $dao;
        $this->ugroup_manager      = $ugroup_manager;
        $this->normalizer          = $normalizer;
        $this->permissions_manager = $permissions_manager;
        $this->validator           = $validator;
        $this->sorter              = $sorter;
    }

    public function getUpdatedPermissionsFromRequest(Codendi_Request $request, GitRepository $repository)
    {
        $updated_permissions = array();

        $this->updateWriters($request, $repository, $updated_permissions);
        $this->updateRewinders($request, $repository, $updated_permissions);

        return $updated_permissions;
    }

    private function getAllWriters(Codendi_Request $request)
    {
        $branches = $request->get(self::EDIT_BRANCH_PREFIX . "-write");
        if (! is_array($branches)) {
            $branches = array();
        }

        $tags = $request->get(self::EDIT_TAG_PREFIX . "-write");
        if (! is_array($tags)) {
            $tags = array();
        }

        return $branches + $tags;
    }

    private function updateWriters(Codendi_Request $request, GitRepository $repository, array &$updated_permissions)
    {
        $all_writers     = $this->getAllWriters($request);
        $all_permissions = $this->getBranchesFineGrainedPermissionsForRepository($repository) +
            $this->getTagsFineGrainedPermissionsForRepository($repository);

        $remaining_permissions = $this->setWritersForPermissionsInRequest(
            $request,
            $all_permissions,
            $all_writers,
            $updated_permissions
        );

        $this->setEmptyWritersForPermissionsNotInRequest(
            $request,
            $remaining_permissions,
            $all_writers,
            $updated_permissions
        );
    }

    private function setWritersForPermissionsInRequest(
        Codendi_Request $request,
        array $all_permissions,
        array $all_writers,
        array &$updated_permissions
    ) {
        foreach ($all_writers as $permission_id => $writers) {
            $permission = $all_permissions[$permission_id];
            unset($all_permissions[$permission_id]);

            if (! $permission || ! $this->hasChangesInWriters($permission, $writers)) {
                continue;
            }

            if (! isset($updated_permissions[$permission_id])) {
                $updated_permissions[$permission_id] = $permission;
            }

            $updated_permissions[$permission_id]->setWriters(
                $this->buildUgroups($request->getProject(), $all_writers, $permission_id)
            );
        }

        return $all_permissions;
    }

    private function setEmptyWritersForPermissionsNotInRequest(
        Codendi_Request $request,
        array $remaining_permissions,
        array $all_writers,
        array &$updated_permissions
    ) {
        foreach ($remaining_permissions as $permission_id => $permission) {
            if (! $this->hasChangesInWriters($permission, array())) {
                continue;
            }

            if (! isset($updated_permissions[$permission_id])) {
                $updated_permissions[$permission_id] = $permission;
            }

            $updated_permissions[$permission_id]->setWriters(
                $this->buildUgroups($request->getProject(), $all_writers, $permission_id)
            );
        }
    }

    private function getAllRewinders(Codendi_Request $request)
    {
        $branches = $request->get(self::EDIT_BRANCH_PREFIX . "-rewind");
        if (! is_array($branches)) {
            $branches = array();
        }

        $tags = $request->get(self::EDIT_TAG_PREFIX . "-rewind");
        if (! is_array($tags)) {
            $tags = array();
        }

        return $branches + $tags;
    }

    private function updateRewinders(Codendi_Request $request, GitRepository $repository, array &$updated_permissions)
    {
        $all_rewinders   = $this->getAllRewinders($request);
        $all_permissions = $this->getBranchesFineGrainedPermissionsForRepository($repository) +
            $this->getTagsFineGrainedPermissionsForRepository($repository);

        $remaining_permissions = $this->setRewindersForPermissionsInRequest(
            $request,
            $all_permissions,
            $all_rewinders,
            $updated_permissions
        );

        $this->setEmptyRewindersForPermissionsNotInRequest(
            $request,
            $remaining_permissions,
            $all_rewinders,
            $updated_permissions
        );
    }

    private function setRewindersForPermissionsInRequest(
        Codendi_Request $request,
        array $all_permissions,
        array $all_rewinders,
        array &$updated_permissions
    ) {
        foreach ($all_rewinders as $permission_id => $rewinders) {
            $permission = $all_permissions[$permission_id];
            unset($all_permissions[$permission_id]);

            if (! $permission || ! $this->hasChangesInRewinders($permission, $rewinders)) {
                continue;
            }

            if (! isset($updated_permissions[$permission_id])) {
                $updated_permissions[$permission_id] = $permission;
            }

            $updated_permissions[$permission_id]->setRewinders(
                $this->buildUgroups($request->getProject(), $all_rewinders, $permission_id)
            );
        }

        return $all_permissions;
    }

    private function setEmptyRewindersForPermissionsNotInRequest(
        Codendi_Request $request,
        array $remaining_permissions,
        array $all_rewinders,
        array &$updated_permissions
    ) {
        foreach ($remaining_permissions as $permission_id => $permission) {
            if (! $this->hasChangesInRewinders($permission, array())) {
                continue;
            }

            if (! isset($updated_permissions[$permission_id])) {
                $updated_permissions[$permission_id] = $permission;
            }

            $updated_permissions[$permission_id]->setRewinders(
                $this->buildUgroups($request->getProject(), $all_rewinders, $permission_id)
            );
        }
    }

    private function hasChangesInWriters(FineGrainedPermission $permission, array $ugroup_ids)
    {
        $current_ugroup_ids = array();
        foreach ($permission->getWritersUgroup() as $writer) {
            $current_ugroup_ids[] = $writer->getId();
        }

        return $this->hasChanges($current_ugroup_ids, $ugroup_ids);
    }

    private function hasChangesInRewinders(FineGrainedPermission $permission, array $ugroup_ids)
    {
        $current_ugroup_ids = array();
        foreach ($permission->getRewindersUgroup() as $rewinder) {
            $current_ugroup_ids[] = $rewinder->getId();
        }

        return $this->hasChanges($current_ugroup_ids, $ugroup_ids);
    }

    private function hasChanges(array $current_ugroup_ids, array $ugroup_ids)
    {
        return (bool) array_diff($current_ugroup_ids, $ugroup_ids) ||
               array_diff($ugroup_ids, $current_ugroup_ids);
    }

    public function getBranchesFineGrainedPermissionsFromRequest(Codendi_Request $request, GitRepository $repository)
    {
        return $this->buildRepresentationFromRequest($request, $repository, self::ADD_BRANCH_PREFIX);
    }

    public function getTagsFineGrainedPermissionsFromRequest(Codendi_Request $request, GitRepository $repository)
    {
        return $this->buildRepresentationFromRequest($request, $repository, self::ADD_TAG_PREFIX);
    }

    private function buildRepresentationFromRequest(Codendi_Request $request, GitRepository $repository, $prefix)
    {
        $permissions              = array();
        $patterns                 = $request->get("$prefix-name");
        $are_we_activating_regexp = $request->get("use-regexp");

        if ($patterns) {
            foreach ($patterns as $index => $pattern) {
                if (! $this->validator->isValidForRepository($repository, $pattern, $are_we_activating_regexp)) {
                    $GLOBALS['Response']->addFeedback(
                        Feedback::WARN,
                        $GLOBALS['Language']->getText(
                            'plugin_git',
                            'fine_grained_bad_pattern',
                            $pattern
                        )
                    );

                    continue;
                }

                $writers   = $this->getWritersFromRequest($request, $index, $prefix);
                $rewinders = $this->getRewindersFromRequest($request, $index, $prefix);

                $permissions[] = new FineGrainedPermission(
                    0,
                    $repository->getId(),
                    $pattern,
                    $writers,
                    $rewinders
                );
            }
        }

        return $permissions;
    }

    private function getWritersFromRequest(Codendi_Request $request, $index, $prefix)
    {
        $all_ugroup_ids = $request->get("$prefix-write") ? $request->get("$prefix-write") : array();

        return $this->buildUgroups($request->getProject(), $all_ugroup_ids, $index);
    }

    private function getRewindersFromRequest(Codendi_Request $request, $index, $prefix)
    {
        $all_ugroup_ids = $request->get("$prefix-rewind") ? $request->get("$prefix-rewind") : array();

        return $this->buildUgroups($request->getProject(), $all_ugroup_ids, $index);
    }

    /**
     * @return array
     */
    private function buildUgroups(Project $project, array $all_ugroup_ids, $index)
    {
        $ugroups    = array();
        $collection = new PermissionsNormalizerOverrideCollection();

        if (isset($all_ugroup_ids[$index])) {
            $normalized_ugroup_ids = $this->normalizer->getNormalizedUGroupIds(
                $project,
                $all_ugroup_ids[$index],
                $collection
            );

            foreach ($normalized_ugroup_ids as $ugroup_id) {
                $ugroups[] = $this->ugroup_manager->getById($ugroup_id);
            }
        }

        $collection->emitFeedback('');
        return $ugroups;
    }

    public function getBranchesFineGrainedPermissionsForRepository(GitRepository $repository)
    {
        $permissions = array();

        foreach ($this->dao->searchBranchesFineGrainedPermissionsForRepository($repository->getId()) as $row) {
            $permission                        = $this->getInstanceFromRow($row);
            $permissions[$permission->getId()] = $permission;
        }

        return $this->sorter->sort($permissions);
    }

    public function getTagsFineGrainedPermissionsForRepository(GitRepository $repository)
    {
        $permissions = array();

        foreach ($this->dao->searchTagsFineGrainedPermissionsForRepository($repository->getId()) as $row) {
            $permission                        = $this->getInstanceFromRow($row);
            $permissions[$permission->getId()] = $permission;
        }

        return $this->sorter->sort($permissions);
    }

    /**
     * @return UGroups[]
     */
    private function getWritersForPermission($permission_id)
    {
        $ugroups = array();

        foreach ($this->dao->searchWriterUgroupIdsForFineGrainedPermissions($permission_id) as $row) {
            $ugroups[] = $this->ugroup_manager->getById($row['ugroup_id']);
        }

        return $ugroups;
    }

    /**
     * @return UGroups[]
     */
    private function getRewindersForPermission($permission_id)
    {
        $ugroups = array();

        foreach ($this->dao->searchRewinderUgroupIdsForFineGrainePermissions($permission_id) as $row) {
            $ugroups[] = $this->ugroup_manager->getById($row['ugroup_id']);
        }

        return $ugroups;
    }

    private function getInstanceFromRow(array $row)
    {
        $permission_id = $row['id'];

        return new FineGrainedPermission(
            $permission_id,
            $row['repository_id'],
            $row['pattern'],
            $this->getWritersForPermission($permission_id),
            $this->getRewindersForPermission($permission_id)
        );
    }

    public function getDefaultBranchesFineGrainedPermissionsForRepository(GitRepository $repository)
    {
        return array(
            $this->buildDefaultForRepository($repository)
        );
    }

    public function getDefaultTagsFineGrainedPermissionsForRepository(GitRepository $repository)
    {
        return array(
            $this->buildDefaultForRepository($repository)
        );
    }

    private function buildDefaultForRepository(GitRepository $repository)
    {
        $writers = array();
        foreach ($this->permissions_manager->getAuthorizedUgroupIds($repository->getId(), Git::PERM_WRITE) as $id) {
            $writers[] = $this->ugroup_manager->getById($id);
        }

        $rewinders = array();
        foreach ($this->permissions_manager->getAuthorizedUgroupIds($repository->getId(), Git::PERM_WPLUS) as $id) {
            $rewinders[] = $this->ugroup_manager->getById($id);
        }

        return new FineGrainedPermission(
            0,
            $repository->getId(),
            '*',
            $writers,
            $rewinders
        );
    }
}
