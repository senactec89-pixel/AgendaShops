<?php use AgendaShops\Http\Forms as F; use AgendaShops\Modules\Identity\GoogleAuth; ?>
<main class="page-shell narrow"><section class="card"><span class="pill">Sua conta</span><h1><?= e($page['title']) ?></h1>
<?php if (in_array($page['mode'],['register','setup'],true)): ?>
<?= F::open($page['mode'] === 'setup' ? 'setup' : 'register',isset($page['setup_token']) ? ['setup_token'=>$page['setup_token']] : []) ?>
<?= F::input('name','Nome completo','','text','required maxlength="100" autocomplete="name"') ?>
<?= F::input('email','E-mail','','email','required autocomplete="email"') ?>
<?= F::input('phone','Telefone com DDD','','tel','required autocomplete="tel"') ?>
<?= F::input('password','Senha (10 a 72 caracteres)','','password','required minlength="10" maxlength="72" autocomplete="new-password"') ?>
<?= F::input('password_confirmation','Confirme a senha','','password','required minlength="10" maxlength="72" autocomplete="new-password"') ?>
<p>Leia os <a href="/termos">termos de uso</a> e a <a href="/privacidade">política de privacidade</a>.</p>
<?= F::check('consent','Li e aceito os termos e a política de privacidade (versão 23/10/2026).') ?>
<?= F::close('Criar minha conta') ?>
<?php elseif ($page['mode'] === 'login'): ?>
<?= F::open('login') . F::input('email','E-mail','','email','required autocomplete="email"') . F::input('password','Senha','','password','required autocomplete="current-password"') . F::close('Entrar') ?>
<p><a href="/recuperar">Esqueci minha senha</a> · <a href="/cadastro">Criar conta</a></p>
<?php elseif ($page['mode'] === 'forgot'): ?>
<p>Informe seu e-mail para receber um link de recuperação.</p>
<?= F::open('forgot') . F::input('email','E-mail','','email','required autocomplete="email"') . F::close('Enviar instruções') ?>
<?php else: ?>
<?= F::open('reset',['token'=>is_string($_GET['token'] ?? null) ? $_GET['token'] : '']) . F::input('password','Nova senha','','password','required minlength="10" maxlength="72" autocomplete="new-password"') . F::input('password_confirmation','Confirme a senha','','password','required autocomplete="new-password"') . F::close('Redefinir senha') ?>
<?php endif; ?>
<?php if (in_array($page['mode'],['login','register'],true) && GoogleAuth::configured()): ?>
<hr><?= F::open('google') ?>
<?php if ($page['mode'] === 'register'): ?><?= F::check('consent','Li e aceito os termos de uso e a política de privacidade.') ?><?php endif; ?>
<?= F::close('Continuar com Google') ?>
<?php endif; ?>
</section></main>
