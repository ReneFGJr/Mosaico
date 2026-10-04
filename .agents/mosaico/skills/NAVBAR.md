# NAVBAR.md — Diretrizes do Menu Superior

## 1. Finalidade

O menu superior do **MOSAICO — Repositório de Ativos Digitais** deve identificar o sistema, oferecer acesso às ações globais da sessão e ocupar o mínimo de espaço vertical necessário.

Estas diretrizes se aplicam a todas as telas autenticadas e públicas do sistema.

## 2. Identidade visual

- Use exclusivamente o logo `public/assets/logo/logo_mosaico.png`.
- O arquivo do logo já contém o símbolo e o nome da marca. Não repita “MOSAICO” ou “MOSAICO repository” em texto ao lado da imagem.
- O link da marca deve conduzir ao painel (`/painel`) quando o usuário estiver autenticado.
- O link deve possuir `aria-label="MOSAICO — Repositório de Ativos Digitais"`.
- A imagem deve possuir texto alternativo curto e significativo, como `alt="MOSAICO repository"`.
- Não distorça, corte, rotacione ou altere as cores do logo.
- Preserve a proporção original usando `object-fit: contain` e alinhamento à esquerda.

## 3. Dimensões e espaçamento

### Desktop

- Altura da navbar: `72px`.
- Padding da navbar: `0`.
- Margem esquerda do bloco da marca: `32px`.
- Área máxima do logo: `240px × 64px`.
- Margem direita da área do usuário: `32px`.

### Dispositivos móveis

- Altura da navbar: `64px`.
- Padding da navbar: `0`.
- Margem esquerda do bloco da marca: `16px`.
- Área máxima do logo: `190px × 56px`.
- Margem direita da área do usuário: `16px`.

Ao alterar a altura da navbar, ajuste conjuntamente:

- o `padding-top` do `body`;
- a posição `top` da sidebar fixa em telas grandes;
- quaisquer âncoras ou elementos fixos que possam ficar encobertos.

## 4. Estrutura

A navbar deve conter somente:

1. marca do MOSAICO à esquerda;
2. identificação resumida do usuário à direita, quando houver sessão;
3. ação “Sair” à direita, quando houver sessão.

Os links de navegação para **Visão geral**, **Comunidades** e **Peças** pertencem à sidebar, não à navbar.

Não adicione breadcrumbs, filtros, títulos de página, botões de cadastro ou mensagens de sistema ao menu superior.

## 5. Comportamento

- Mantenha a navbar fixa no topo em telas grandes.
- Use fundo branco e borda inferior discreta, coerente com os tokens visuais do sistema.
- Garanta que o conteúdo da página nunca fique oculto atrás da navbar.
- Em telas estreitas, esconda apenas informações secundárias, como o nome completo do usuário; mantenha disponíveis a marca e a ação de saída.
- Evite menus suspensos quando houver apenas uma ação global.
- Não aumente a navbar para acomodar textos duplicados ou elementos específicos de uma página.

## 6. Acessibilidade

- Inclua antes da navbar um link “Pular para o conteúdo”, visível ao receber foco.
- Use o elemento semântico `<header>` para a navbar.
- Todos os controles devem ser acessíveis por teclado.
- Preserve indicador de foco com contraste suficiente.
- A ação de logout deve ser um formulário `POST` protegido por CSRF, nunca um link `GET`.
- O nome do usuário não deve ser a única indicação de que uma sessão está ativa.
- Não use apenas cor para indicar estados ou ações.
- Mantenha a ordem visual igual à ordem de foco.
- Garanta uma área interativa de pelo menos `44 × 44px` para controles em telas sensíveis ao toque.

## 7. Responsividade

- Use o breakpoint principal de `850px`, acompanhando o layout geral do sistema.
- O logo deve reduzir de tamanho sem perder legibilidade ou proporção.
- A navbar não deve provocar rolagem horizontal.
- Textos secundários podem ser ocultados no mobile, mas a ação “Sair” deve permanecer identificável.
- Teste larguras de `320px`, `375px`, `768px`, `1024px` e superiores.

## 8. Segurança e privacidade

- Não exiba e-mail, papel, permissões ou outros dados pessoais desnecessários no menu superior.
- Escape todo conteúdo dinâmico antes de renderizá-lo.
- Não inclua tokens, IDs internos ou informações de depuração no HTML.
- Após o logout, destrua a sessão e redirecione para `/login`.

## 9. Referência de implementação

Estrutura recomendada:

```php
<header class="navbar">
    <a
        class="brand"
        href="<?= site_url('painel') ?>"
        aria-label="MOSAICO — Repositório de Ativos Digitais"
    >
        <img
            src="<?= base_url('assets/logo/logo_mosaico.png') ?>"
            alt="MOSAICO repository"
        >
    </a>

    <?php if (session('user_id')): ?>
        <div class="user">
            <span><?= esc(session('user_name')) ?></span>
            <form method="post" action="<?= site_url('logout') ?>">
                <?= csrf_field() ?>
                <button class="link-button" type="submit">Sair</button>
            </form>
        </div>
    <?php endif ?>
</header>
```

CSS de referência:

```css
.navbar {
    position: fixed;
    z-index: 10;
    inset: 0 0 auto;
    height: 72px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border-bottom: 1px solid var(--soft);
}

.brand {
    margin-left: 32px;
}

.brand img {
    display: block;
    width: 240px;
    height: 64px;
    object-fit: contain;
    object-position: left center;
}

.user {
    margin-right: 32px;
}

@media (max-width: 850px) {
    .navbar {
        height: 64px;
    }

    .brand {
        margin-left: 16px;
    }

    .brand img {
        width: 190px;
        height: 56px;
    }

    .user {
        margin-right: 16px;
    }
}
```

## 10. Critérios de validação

Antes de concluir uma alteração na navbar, confirme que:

- o logo aparece uma única vez e sem texto duplicado;
- a proporção do logo está preservada;
- a navbar não ocupa mais de `72px` no desktop nem mais de `64px` no mobile;
- o conteúdo e a sidebar começam abaixo da navbar;
- não existe rolagem horizontal;
- os controles funcionam com teclado;
- o foco é claramente visível;
- o logout utiliza `POST` e CSRF;
- o layout funciona com zoom de `200%`;
- a versão móvel mantém marca e logout utilizáveis.
