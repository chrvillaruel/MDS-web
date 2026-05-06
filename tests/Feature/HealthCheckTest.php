<?php

declare(strict_types=1);

it('responds to the health check route', function (): void {
    $this->get('/up')->assertOk();
});

it('renders the welcome page via Inertia', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});
