<?php

namespace App\Http\Controllers\Company;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\ImportUsersRequest;
use App\Http\Requests\Company\StoreUserRequest;
use App\Http\Requests\Company\UpdateUserRequest;
use App\Http\Resources\Company\UserResource;
use App\Interfaces\Company\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function index(Request $request)
    {
        $data = $this->userRepository->index(
            $request->query('search'),
            $request->integer('per_page', 15)
        );

        return UserResource::collection($data)->additional(['success' => true]);
    }

    public function export(Request $request)
    {
        $search = $request->query('search');
        $rows = $this->userRepository->all($search);
        $filename = 'company-users-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['fin', 'name', 'surname', 'father', 'dob', 'email', 'status', 'status_label']);

            foreach ($rows as $user) {
                fputcsv($handle, [
                    $user->fin,
                    $user->name,
                    $user->surname,
                    $user->father,
                    $user->dob?->format('Y-m-d'),
                    $user->email,
                    $user->status?->value,
                    $user->status?->description(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Bulk-import company users from an uploaded CSV. Each row is
     * validated with the exact same rules a single store() already
     * enforces (StoreUserRequest::rules()). A row failing validation does
     * not abort the batch -- it is reported per row -- but a structurally
     * malformed file (wrong/missing header) is rejected up front, before
     * any row is processed.
     */
    public function import(ImportUsersRequest $request)
    {
        $expectedHeader = ['fin', 'name', 'surname', 'father', 'dob', 'email', 'status'];

        $handle = new \SplFileObject($request->file('file')->getRealPath());
        $handle->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $handle->rewind();
        $header = $handle->current();
        $normalizedHeader = is_array($header) ? array_map(fn ($h) => strtolower(trim((string) $h)), $header) : null;

        if ($normalizedHeader !== $expectedHeader) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid CSV header. Expected columns, in order: '.implode(',', $expectedHeader).'.',
            ], 422);
        }

        $rules = (new StoreUserRequest())->rules();
        $created = 0;
        $errors = [];
        $seenFins = [];
        $seenEmails = [];
        $rowNumber = 1; // the header itself is row 1

        // Deliberately a while/next loop, not foreach: SplFileObject's
        // Iterator implementation calls rewind() at the start of a
        // foreach, which would reset the cursor back to the header line
        // we already consumed above.
        $handle->next();
        while ($handle->valid()) {
            $row = $handle->current();
            $rowNumber++;

            if (!is_array($row) || $row === [null]) {
                $handle->next();

                continue; // trailing blank line (SplFileObject may yield `false` or `[null]` past the last real row)
            }

            if (count($row) !== count($expectedHeader)) {
                $errors[] = ['row' => $rowNumber, 'fin' => null, 'messages' => ['Row has '.count($row).' column(s), expected '.count($expectedHeader).'.']];
                $handle->next();

                continue;
            }

            $data = array_combine($expectedHeader, $row);
            $fin = is_string($data['fin']) ? strtoupper(trim($data['fin'])) : $data['fin'];
            $data['fin'] = $fin;
            $email = $data['email'];

            if ($fin !== null && isset($seenFins[$fin])) {
                $errors[] = ['row' => $rowNumber, 'fin' => $fin, 'messages' => ["Duplicate fin '{$fin}' already appears earlier in this file (row {$seenFins[$fin]})."]];
                $handle->next();

                continue;
            }

            if ($email !== null && isset($seenEmails[$email])) {
                $errors[] = ['row' => $rowNumber, 'fin' => $fin, 'messages' => ["Duplicate email '{$email}' already appears earlier in this file (row {$seenEmails[$email]})."]];
                $handle->next();

                continue;
            }

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'fin' => $fin, 'messages' => $validator->errors()->all()];
                $handle->next();

                continue;
            }

            try {
                $this->userRepository->store($validator->validated());
                $created++;
                $seenFins[$fin] = $rowNumber;
                $seenEmails[$email] = $rowNumber;
            } catch (\Throwable $ex) {
                $errors[] = ['row' => $rowNumber, 'fin' => $fin, 'messages' => [$ex->getMessage()]];
            }

            $handle->next();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'created' => $created,
                'failed' => count($errors),
                'errors' => $errors,
            ],
        ], 200);
    }

    public function store(StoreUserRequest $request)
    {
        $details = $request->validated();
        DB::beginTransaction();
        try {
            $user = $this->userRepository->store($details);
            DB::commit();

            return ApiResponseClass::sendResponse(
                new UserResource($user),
                'Company User Create Successful',
                201
            );
        } catch (\Throwable $ex) {
            return ApiResponseClass::rollback($ex);
        }
    }

    public function show($fin)
    {
        $user = $this->userRepository->getByFin($fin);

        return ApiResponseClass::sendResponse(new UserResource($user), '', 200);
    }

    public function update(UpdateUserRequest $request, $fin)
    {
        $details = $request->validated();
        DB::beginTransaction();
        try {
            $user = $this->userRepository->update($details, $fin);
            DB::commit();

            return ApiResponseClass::sendResponse(
                new UserResource($user),
                'Company User Update Successful',
                200
            );
        } catch (\Throwable $ex) {
            return ApiResponseClass::rollback($ex);
        }
    }

    public function destroy($fin)
    {
        DB::beginTransaction();
        try {
            $this->userRepository->delete($fin);
            DB::commit();

            return response()->noContent();
        } catch (\Throwable $ex) {
            return ApiResponseClass::rollback($ex);
        }
    }
}
