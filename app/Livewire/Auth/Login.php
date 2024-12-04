<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Mary\Traits\Toast;

class Login extends Component
{
    use Toast;

    public $title = 'Bem vindo ao Flow!';
    public ?string $email;
    public ?string $password;

    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Tenta fazer o login do usuário
        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $user = Auth::user();

            if ($user->active != 1) {
                Auth::logout();
                $this->error("Sua conta está desativada. Por favor, entre em contato com o suporte.", position: 'toast-top');
                return Redirect::route('login');
            }

            request()->session()->regenerate();
            $this->success('Logged in successfully', position: 'toast-top');
            return Redirect::route('dashboard');
        } else {
            $this->error("Cannot verify the credentials!", position: 'toast-top');
        }
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
