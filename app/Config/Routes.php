<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->match(['get', 'post'], 'login', 'Auth::login');
$routes->post('logout', 'Auth::logout', ['filter' => 'auth']);

$routes->group('', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('profile', 'Profile::index');
    $routes->post('profile/password', 'Profile::password');
    $routes->get('painel', 'Dashboard::index');
    $routes->get('comunidades', 'Projects::index');
    $routes->match(['get', 'post'], 'comunidades/nova', 'Projects::create');
    $routes->post('comunidades/(:segment)/selecionar', 'Projects::select/$1');
    $routes->match(['get', 'post'], 'comunidades/(:segment)/editar', 'Projects::edit/$1');
    $routes->get('comunidades/(:segment)', 'Projects::show/$1');
    $routes->match(['get', 'post'], 'comunidades/(:segment)/colecoes/nova', 'Collections::create/$1');
    $routes->get('colecoes/(:segment)/imagem', 'Collections::coverImage/$1');
    $routes->match(['get', 'post'], 'colecoes/(:segment)/editar', 'Collections::edit/$1');
    $routes->match(['get', 'post'], 'colecoes/(:segment)/trocar-imagem', 'Collections::changeImage/$1');
    $routes->match(['get', 'post'], 'colecoes/(:segment)/deletar', 'Collections::delete/$1');
    $routes->post('colecoes/(:segment)/selecionar', 'Collections::select/$1');
    $routes->get('colecoes/(:segment)', 'Collections::show/$1');
    $routes->post('colecoes/(:segment)/arquivos', 'Collections::upload/$1');
    $routes->post('colecoes/(:segment)/google-drive', 'Collections::importGoogleDrive/$1');
    $routes->post('colecoes/(:segment)/pecas/remover-rascunhos', 'Collections::removeDrafts/$1');
    $routes->post('colecoes/(:segment)/pecas/acoes', 'Collections::bulkAction/$1');
    $routes->match(['get', 'post'], 'comunidades/(:segment)/permissoes', 'Projects::permissions/$1');
    $routes->post('comunidades/(:segment)/visibilidade', 'Projects::visibility/$1');
    $routes->post('comunidades/(:segment)/excluir', 'Projects::delete/$1');
    $routes->get('projetos', static fn () => redirect()->to('/comunidades'));
    $routes->get('projetos/novo', static fn () => redirect()->to('/comunidades/nova'));
    $routes->get('projetos/(:segment)', static fn (string $id) => redirect()->to('/comunidades/' . $id));
    $routes->get('pecas', 'Pieces::index');
    $routes->get('colecoes/(:segment)/pecas', static fn (string $id) => redirect()->to('/colecoes/' . $id));
    $routes->match(['get', 'post'], 'pecas/nova', 'Pieces::create');
    $routes->get('pecas/(:segment)', 'Pieces::show/$1');
    $routes->get('pecas/(:segment)/miniatura', 'Pieces::thumbnail/$1');
    $routes->get('pecas/(:segment)/preview', 'Pieces::preview/$1');
    $routes->post('pecas/(:segment)/nome', 'Pieces::rename/$1');
    $routes->post('pecas/(:segment)/descrever-imagem', 'Pieces::describeImage/$1');
    $routes->post('pecas/(:segment)/detectar-pessoas', 'Pieces::detectPeople/$1');
    $routes->post('pecas/(:segment)/pessoas/nomes', 'Pieces::peopleNames/$1');
    $routes->post('pecas/(:segment)/palavras-chave', 'Pieces::keywords/$1');
    $routes->match(['get', 'post'], 'pecas/(:segment)/ativos/novo', 'Assets::create/$1');
    $routes->match(['get', 'post'], 'ativos/(:segment)/versoes/nova', 'Assets::version/$1');
});
