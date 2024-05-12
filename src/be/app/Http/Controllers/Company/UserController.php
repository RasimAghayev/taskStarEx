<?php

namespace App\Http\Controllers\Company;

use AllowDynamicProperties;
use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreUserRequest;
use App\Http\Requests\Company\UpdateUserRequest;
use App\Http\Resources\Company\UserResource;
use App\Interfaces\Company\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

#[AllowDynamicProperties]
class UserController extends Controller
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepositoryInterface = $userRepository;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = $this->userRepositoryInterface->index();

        return ApiResponseClass::sendResponse(
            UserResource::collection($data),
            '',
            200
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $details = [
            'fin' => $request->fin,
            'name' => $request->name,
            'surname' => $request->surname,
            'father' => $request->father,
            'dob' => $request->dob,
            'email' => $request->email,
            'status' => $request->status,
        ];
        DB::beginTransaction();
        try {
            $user = $this->userRepositoryInterface->store($details);

            DB::commit();

            return ApiResponseClass::sendResponse(
                new UserResource($user),
                'Company User Create Successful',
                201
            );
        } catch (\Exception $ex) {
            return ApiResponseClass::rollback($ex);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($fin)
    {
        $user = $this->userRepositoryInterface->getByFin($fin);

        return ApiResponseClass::sendResponse(
            new UserResource($user),
            '',
            200
        );
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, $fin)
    {
        $updateDetails = [
            'fin' => $request->fin,
            'name' => $request->name,
            'surname' => $request->surname,
            'father' => $request->father,
            'dob' => $request->dob,
            'email' => $request->email,
            'status' => $request->status,
        ];
        DB::beginTransaction();
        try {
            $this->userRepositoryInterface->update($updateDetails, $fin);

            DB::commit();

            return ApiResponseClass::sendResponse(
                'Company User Update Successful',
                '',
                201
            );
        } catch (\Exception $ex) {
            return ApiResponseClass::rollback($ex);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($fin)
    {
        $this->userRepositoryInterface->delete($fin);

        return ApiResponseClass::sendResponse(
            'Company User Delete Successful',
            '',
            204
        );
    }
}
