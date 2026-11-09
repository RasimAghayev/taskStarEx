<?php

namespace App\Http\Controllers\Company;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreUserRequest;
use App\Http\Requests\Company\UpdateUserRequest;
use App\Http\Resources\Company\UserResource;
use App\Interfaces\Company\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
