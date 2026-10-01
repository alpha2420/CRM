@csrf
<div class="form-section">
    <div class="form-grid">
        <label>Name <input name="name" value="{{ old('name', $user->name) }}" required maxlength="100" autofocus></label>
        <label>Email <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="150"></label>
        <label>Role
            <select name="role" required>
                @foreach (\App\Enums\Role::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <span class="hint">Agents see only their own leads. Admins manage everything.</span>
        </label>
        <label class="check" style="align-self:center"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Active (can log in and receive leads)</label>
    </div>
</div>
<div class="form-section">
    <h2>{{ $user->exists ? 'Reset password' : 'Password' }}</h2>
    <span class="hint">{{ $user->exists ? 'Leave blank to keep their current password.' : 'Share it with them; they can change it from their profile.' }}</span>
    <div class="form-grid">
        <label>{{ $user->exists ? 'New password' : 'Password' }} <input type="password" name="password" @required(! $user->exists) minlength="8" autocomplete="new-password"></label>
        <label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password"></label>
    </div>
</div>
