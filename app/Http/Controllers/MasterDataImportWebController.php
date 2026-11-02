<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Tenant;
use App\Modules\DataMigration\Services\MasterDataImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MasterDataImportWebController extends Controller
{
    public function __construct(
        protected MasterDataImportService $importService,
    ) {}

    public function index(): View
    {
        $tenant = TenantContext::get() ?? Tenant::first();
        if ($tenant) {
            TenantContext::set($tenant);
        }

        $company = $tenant ? Company::where('tenant_id', $tenant->id)->first() : null;

        return view('import.master-data', [
            'tenant' => $tenant,
            'company' => $company,
            'result' => null,
            'submitted' => false,
        ]);
    }

    public function upload(Request $request): View
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,csv,txt',
            'type' => 'required|in:products,customers,suppliers',
        ]);

        $tenant = TenantContext::get() ?? Tenant::first();
        if ($tenant) {
            TenantContext::set($tenant);
        }

        $company = $tenant ? Company::where('tenant_id', $tenant->id)->first() : null;
        if (! $company) {
            return view('import.master-data', [
                'tenant' => $tenant,
                'company' => null,
                'result' => [
                    'valid' => false,
                    'errors' => [['row' => 0, 'field' => 'system', 'message' => 'No company found for active tenant.']],
                    'processed' => 0,
                ],
                'submitted' => true,
            ]);
        }

        $file = $request->file('import_file');
        $type = $request->input('type', 'products');
        $dryRun = $request->boolean('dry_run', true);

        try {
            $path = $file->getRealPath();
            $rows = $this->importService->parseFile($path);

            $result = match ($type) {
                'customers' => $this->importService->importCustomers($rows, $company->id, $dryRun),
                'suppliers' => $this->importService->importSuppliers($rows, $company->id, $dryRun),
                default => $this->importService->importProducts($rows, $company->id, $dryRun),
            };
        } catch (Throwable $e) {
            $result = [
                'valid' => false,
                'errors' => [['row' => 0, 'field' => 'file', 'message' => 'Parse error: '.$e->getMessage()]],
                'processed' => 0,
            ];
        }

        return view('import.master-data', [
            'tenant' => $tenant,
            'company' => $company,
            'result' => $result,
            'submitted' => true,
            'dryRun' => $dryRun,
            'type' => $type,
            'fileName' => $file->getClientOriginalName(),
        ]);
    }
}
