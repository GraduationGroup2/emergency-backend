<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthorityCollection;
use App\Http\Resources\Forms\AuthorityResource;
use App\Models\Authority;
use App\Models\AuthorityType;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthorityController extends Controller
{
    public function getAuthorities(Request $request): AuthorityCollection
    {
        $authorities = Authority::query()
            ->select('authorities.*', 'authority_types.name as type')
            ->join('authority_types', 'authority_types.id', '=', 'authorities.authority_type_id');

        return new AuthorityCollection(kaantable($authorities, $request));
    }

    public function getAuthority(int $id): JsonResponse
    {
        $authority = Authority::query()->find($id);
        if (!$authority) {
            return res('Authority not found', 404);
        }

        return res($authority);
    }

    public function updateAuthority(Request $request, int $id): JsonResponse
    {
        $authority = Authority::query()->find($id);
        if (!$authority) {
            return res('Authority not found', 404);
        }

        $validator = validator($request->all(), [
            'first_name' => 'string|max:255',
            'last_name' => 'string|max:255',
            'user_id' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return res($validator->errors(), 400);
        }

        $authority->update($request->all());
        User::find($authority->user_id)->update([
            'phone_number' => $request->phone_number,
        ]);

        return res($authority);
    }

    /**
     * @throws Exception
     */
    public static function createAuthority($data)
    {
        $validator = validator($data, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors());
        }

        return Authority::query()->create($data);
    }

    public function deleteAuthority(int $id): JsonResponse
    {
        $authority = Authority::query()->find($id);
        if (!$authority) {
            return res('Authority not found', 404);
        }

        DB::beginTransaction();
        try {
            $user = User::find($authority->user_id);
            $authority->delete();
            $user->delete();

            DB::commit();
            return res('Authority deleted successfully');
        } catch (Exception $e) {
            return res('Authority could not be deleted', 400);
        }
    }

    public function getAuthorityForm(Request $request, $id): JsonResponse
    {
        $authority = Authority::query()->find($id);
        if (!$authority) {
            return res('Authority not found', 404);
        }

        return res(new AuthorityResource($authority));
    }

    public function getAuthorityCreateForm(Request $request): JsonResponse
    {
        $newAuthority = new Authority();
        return res(new AuthorityResource($newAuthority));
    }


    public function deleteAuthorityById($id) {
        $authority = Authority::find($id);
        if(!$authority) throw new Exception('Authority not found');

        $user = User::find($authority->user_id);
        $authority->delete();
        $user->delete();
    }

    public function bulkDeleteAuthorities(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            foreach($request->ids as $authorityId) {
                $this->deleteAuthorityById($authorityId);
            }
            DB::commit();
            return res('Authorities deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());
            return res('Authorities could not be deleted', 400);
        }
    }

    public function createAuthorityFromForm(Request $request) {
        $validator = validator($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return res($validator->errors(), 400);
        }

        DB::beginTransaction();
        try {
            $type = AuthorityType::query()->find($request->type)->first();
            if(!$type)
            {
                return res('Authority type not found', 404);
            }

            $payload = $request->all();
            $payload['authority_type_id'] = $type->id;

            $user = User::create([
                'email' => $request->email,
                'name' => $request->first_name . ' ' . $request->last_name,
                'password' => $request->password,
                'phone_number' => $request->phone_number,
                'type' => 'authority'
            ]);
            $payload['user_id'] = $user->id;

            Authority::query()->create($payload);

            DB::commit();
            return res('Authority created successfully');
        } catch (Exception $exception) {
            DB::rollBack();
            Log::info($exception->getMessage());
            return res('Authority could not be created', 400);
        }
    }
}
