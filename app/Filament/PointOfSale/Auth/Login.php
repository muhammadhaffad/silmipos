<?php
namespace App\Filament\PointOfSale\Auth;

use Encore\Admin\Auth\Database\Administrator;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as AuthLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends AuthLogin {
    public function getHeading(): string|Htmlable
    {
        return 'SilmiPOS';
    }
    public function getSubheading(): string|Htmlable|null
    {
        return 'Selamat datang di SilmiPOS';
    }
    public function hasLogo(): bool
    {
        return false;
    }
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getLoginFormComponent(), 
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getLoginFormComponent(): Component {
        return Select::make('id')
            ->label('Pegawai')
            ->options(Administrator::all()->pluck('name', 'id'))
            ->native(false)
            ->required()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'id' => $data['id'],
            'password' => $data['password'],
        ];
    }
}