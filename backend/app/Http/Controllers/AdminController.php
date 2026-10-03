<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Show admin dashboard and users management.
     */
    public function index(): View
    {
        return view('pages.admin');
    }

    /**
     * Store a new staff user.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'role' => ['required', 'string', 'in:ADMIN,SALES_MANAGER,WAREHOUSE_MANAGER,CASHIER'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'status' => 'ACTIVE',
            'is_active' => true,
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.index')->with('success', 'Yangi xodim muvaffaqiyatli qo\'shildi.');
    }

    /**
     * Toggle active/blocked status for a staff user.
     */
    public function toggleUserStatus(User $user): RedirectResponse
    {
        if ($user->isOwner() || auth()->id() === $user->id) {
            return redirect()->route('admin.index')->withErrors([
                'error' => 'Asosiy do\'kon egasining holatini o\'zgartirish mumkin emas.',
            ]);
        }

        $newStatus = $user->isActive() ? 'BLOCKED' : 'ACTIVE';
        $user->update([
            'status' => $newStatus,
            'is_active' => $newStatus === 'ACTIVE',
        ]);

        // Invalidate user tokens if blocked
        if ($newStatus === 'BLOCKED') {
            $user->tokens()->delete();
        }

        return redirect()->route('admin.index')->with('success', "Xodim holati yangilandi: {$newStatus}");
    }
}
