<?php

namespace Modules\Construction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ProjectStructure;

class ProjectStructureController extends BaseController
{
    public function store(Request $request, int $project)
    {
        $this->authorizePermission('construction.project.update');
        $project = $this->findProject($project);
        $businessId = $this->businessId();

        $validated = $request->validate([
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('construction_project_structures', 'id')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->where('project_id', $project->id)),
            ],
            'type' => ['required', Rule::in(ProjectStructure::TYPES)],
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:190'],
        ]);

        $project->structures()->create([
            'business_id' => $businessId,
            'parent_id' => $validated['parent_id'] ?? null,
            'type' => $validated['type'],
            'code' => isset($validated['code']) ? trim($validated['code']) : null,
            'name' => trim($validated['name']),
            'sort_order' => ((int) $project->structures()->max('sort_order')) + 1,
        ]);

        return redirect()->route('construction.projects.show', $project->id)->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.structure_created'),
        ]);
    }

    public function destroy(int $project, int $structure)
    {
        $this->authorizePermission('construction.project.update');
        $project = $this->findProject($project);
        $structure = ProjectStructure::query()
            ->where('business_id', $this->businessId())
            ->where('project_id', $project->id)
            ->findOrFail($structure);

        abort_if($structure->children()->exists(), 422, __('construction::lang.structure_has_children'));
        $structure->delete();

        return redirect()->route('construction.projects.show', $project->id)->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.structure_deleted'),
        ]);
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }
}
