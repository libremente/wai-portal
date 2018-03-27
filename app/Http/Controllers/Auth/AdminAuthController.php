<?php

namespace App\Http\Controllers\Auth;

use App\Jobs\ClearPasswordResetToken;
use App\Jobs\SendPasswordResetEmail;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    use ThrottlesLogins;

    protected $maxAttempts = 3;
    protected $decayMinutes = 5;

    /**
     * Show the login form or redirect to admin dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        if (auth()->check() && auth()->user()->can('access-backoffice')) {
            return redirect()->route('admin-dashboard');
        }

        return view('auth.admin_login');
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response|void
     *
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        session()->invalidate();

        if (auth()->attempt($request->only('email', 'password'))) {
            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);

        return back()->withMessage(['error' => __('auth.failed')]);//$this->sendFailedLoginResponse($request);
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendLoginResponse(Request $request)
    {
        $this->clearLoginAttempts($request);
        return redirect()->intended(route('admin-dashboard', [], false));
    }

    /**
     * Get the failed login response instance.
     *
     * @return void
     *
     * @throws ValidationException
     */
    protected function sendFailedLoginResponse()
    {
        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Log the user out of the application.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        auth()->logout();
        session()->invalidate();
        return redirect()->home();
    }

    /**
     * Display the password reset form.
     *
     * @return \Illuminate\View\View
     */
    public function showPasswordForgotForm()
    {
        return view('auth.admin_password_forgot');
    }

    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendPasswordForgotEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = $request->input('email');

        $user = User::where('email', $email)->first();
        if (empty($user) || $user->cant('access-backoffice') || $user->status != 'active') {
            return redirect()->home()->withMessage(['info' => "Se l'indirizzo email inserito corrisponde ad un account amministrativo registrato e attivo, riceverai e breve un messaggio con le istruzioni per il reset della password."]);
        }

        if (!empty($user->passwordResetToken)) {
            $user->passwordResetToken->delete();
        }

        $token = hash_hmac('sha256', str_random(40), config('app.key'));
        $user->passwordResetToken()->create([
            'token' => Hash::make($token),
            'created_at' => now()
        ]);

        $user->load('passwordResetToken');

        dispatch(new SendPasswordResetEmail($user, $token));
        dispatch(new ClearPasswordResetToken($user->passwordResetToken))->delay(now()->addHour());

        return redirect()->home()->withMessage(['info' => "Se l'indirizzo email inserito corrisponde ad un account amministrativo registrato e attivo, riceverai e breve un messaggio con le istruzioni per il reset della password."]);
    }

    /**
     * Display the password reset view for the given token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $token
     * @return \Illuminate\View\View
     */
    public function showPasswordResetForm(Request $request, $token = null)
    {
        $token = $token ?: $request->input('token');

        return view('auth.admin_password_reset')->with(['token' => $token]);
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function passwordReset(Request $request)
    {
        $validatedData = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).+$/'
            ]
        ]);

        $user = User::where('email', $validatedData['email'])->first();

        if (empty($user)) {
            return back()->withMessage(['error' => "L'indirizzo email inserito non è valido oppure il codice è scaduto o errato."]); //TODO: put message in lang file
        }

        if (empty($user->passwordResetToken) || !Hash::check($validatedData['token'], $user->passwordResetToken->token)) {
            return back()->withMessage(['error' => "L'indirizzo email inserito non è valido oppure il codice è scaduto o errato."]); //TODO: put message in lang file
        }

        $user->password = Hash::make($validatedData['password']);
        $user->save();
        $user->passwordResetToken->delete();

        event(new PasswordReset($user));

        auth()->login($user);

        return redirect()->route('admin-dashboard')->withMessage(['success' => trans(Password::PASSWORD_RESET)]);
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'email';
    }
}
