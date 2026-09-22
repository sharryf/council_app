<?php

namespace App\Filament\Auth\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Restyles the stock Filament login form to match Oceancy's brand:
 * icons inside the email/password fields, "Remember me" and "Forgot
 * password?" sharing one row (instead of the link living inside the
 * password field's hint, which is where the base class puts it), and a
 * gradient Sign In button. Used by every panel — see each
 * *PanelProvider's ->login(Login::class) call — since the styling is
 * identical everywhere; the matching CSS lives in
 * resources/views/filament/branding/login-side-panel.blade.php,
 * already loaded on every "simple" auth page via a render hook, so
 * this class needs none of its own.
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                Grid::make(2)
                    ->schema([
                        $this->getRememberFormComponent(),
                        $this->getForgotPasswordLinkComponent(),
                    ])
                    ->extraAttributes(['class' => 'oceancy-remember-row']),
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->prefixIcon(Heroicon::OutlinedEnvelope);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            // The base class puts the "Forgot password?" link here as a
            // hint under the field — cleared because Grid above puts it
            // next to Remember me instead.
            ->hint(null)
            ->prefixIcon(Heroicon::OutlinedLockClosed);
    }

    /**
     * Same link/visibility condition as the base class's password-field
     * hint (see getPasswordFormComponent() there) — just moved.
     */
    protected function getForgotPasswordLinkComponent(): Component
    {
        return Html::make(fn (): HtmlString => filament()->hasPasswordReset()
            ? new HtmlString(Blade::render(
                '<div style="display: flex; justify-content: flex-end;">'
                .'<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="-1">'
                .e(__('filament-panels::auth/pages/login.actions.request_password_reset.label'))
                .'</x-filament::link>'
                .'</div>',
            ))
            : new HtmlString(''));
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->icon(Heroicon::OutlinedArrowRight)
            ->iconPosition(IconPosition::After)
            ->extraAttributes(['class' => 'oceancy-signin-btn']);
    }
}
