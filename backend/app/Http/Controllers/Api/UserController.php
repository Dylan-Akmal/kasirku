<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'is_active', 'created_at')
            ->when($request->query('q'), function ($query, $q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($request->query('role'), fn ($query, $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['admin', 'kasir'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create($data);
        $user->refresh();

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_active']),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes', 'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['admin', 'kasir'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $deactivating = $request->has('is_active') && ! $request->boolean('is_active');

        if ($request->user()->id === $user->id) {
            if ($deactivating) {
                return response()->json([
                    'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ], 422);
            }

            if (isset($data['role']) && $data['role'] !== 'admin') {
                return response()->json([
                    'message' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
                ], 422);
            }
        }

        $user->update($data);

        if ($deactivating || isset($data['password'])) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Akun berhasil diperbarui.',
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_active']),
        ]);
    }
}