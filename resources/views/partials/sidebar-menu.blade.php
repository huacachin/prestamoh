@php
    $items        = is_array($items) ? $items : [];
    $level        = $level ?? 0;
    $currentRoute = Route::currentRouteName() ?? '';

    // --- Helpers de estado/visibilidad ---

    // ¿Item (o cualquiera de sus hijos) coincide con la ruta actual?
    if (! function_exists('isActiveItem')) {
        function isActiveItem(array $item, string $currentRoute): bool
        {
            if (!empty($item['route']) && request()->routeIs($item['route'])) {
                return true;
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isActiveItem($child, $currentRoute)) return true;
                }
            }
            return false;
        }
    }

    // ¿Pasa chequeos de permiso/rol para mostrarse a sí mismo?
    if (! function_exists('passesAuth')) {
        function passesAuth(array $item): bool
        {
            $u = auth()->user();
            if (!$u) return false; // sidebar solo en sesión

            // Si no hay restricciones declaradas, el item "propio" pasa
            $ok = true;

            // 'can' => 'permiso' | ['perm1','perm2'] (por defecto ALL)
            if (isset($item['can'])) {
                if (is_array($item['can'])) {
                    $ok = $ok && $u->hasAllPermissions($item['can']);
                } else {
                    $ok = $ok && $u->can($item['can']);
                }
            }

            // 'canAny' => ['perm1','perm2'] (OR)
            if (isset($item['canAny'])) {
                $ok = $ok && $u->hasAnyPermission((array) $item['canAny']);
            }

            // 'canAll' => ['perm1','perm2'] (AND)
            if (isset($item['canAll'])) {
                $ok = $ok && $u->hasAllPermissions((array) $item['canAll']);
            }

            // Opcional: roles
            if (isset($item['role'])) {
                $ok = $ok && $u->hasRole($item['role']);
            }
            if (isset($item['roleAny'])) {
                $ok = $ok && $u->hasAnyRole((array) $item['roleAny']);
            }
            if (isset($item['roleAll'])) {
                $ok = $ok && $u->hasAllRoles((array) $item['roleAll']);
            }

            return $ok;
        }
    }

    // Visible si él mismo pasa permisos O si tiene algún hijo visible
    if (! function_exists('isVisibleItem')) {
        function isVisibleItem(array $item, string $currentRoute): bool
        {
            $self = passesAuth($item);

            if (!empty($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isVisibleItem($child, $currentRoute)) return true;
                }
            }
            return $self;
        }
    }

    // Filtra items visibles a este nivel
    $visibleItems = array_values(array_filter($items, fn($it) => isVisibleItem($it, $currentRoute)));
@endphp

<ul
    class="{{ $level === 0 ? 'main-nav p-0 mt-2' : 'collapse' }}{{ ($level > 0 && isset($parentId) && isActiveItem(['children' => $visibleItems], $currentRoute)) ? ' show' : '' }}"
    @if($level > 0 && isset($parentId)) id="{{ $parentId }}" @endif
>
    @foreach($visibleItems as $item)
        @php
            $hasChildren = !empty($item['children']) && is_array($item['children']);

            // Filtra hijos visibles antes de renderizar recursivamente
            $childItems = $hasChildren
                ? array_values(array_filter($item['children'], fn($ch) => isVisibleItem($ch, $currentRoute)))
                : [];

            $hasVisibleChildren = $hasChildren && count($childItems) > 0;

            // Activo si la hoja coincide (solo hojas reciben 'active')
            $isLeafActive = !$hasChildren && !empty($item['route']) && request()->routeIs($item['route']);
            $activeClass  = $isLeafActive ? ' active-menu' : '';
        @endphp

        {{-- 1) Título de sección (siempre visible; agrega 'can' si quieres condicionarlo) --}}
        @if(($item['type'] ?? '') === 'title')
            <li class="menu-title">
                <span>{{ $item['title'] }}</span>
            </li>

            {{-- 2) Item con submenú (padre) --}}
        @elseif($hasVisibleChildren)
            <li>
                <a
                    data-bs-toggle="collapse"
                    href="#{{ $item['id'] }}"
                    aria-expanded="{{ isActiveItem(['children'=>$childItems], $currentRoute) ? 'true' : 'false' }}"
                >
                    @isset($item['icon'])
                        <i class="{{ $item['icon'] }}"></i>
                    @endisset
                    {{ $item['title'] }}
                    @isset($item['badge'])
                        <span class="badge {{ $item['badge']['class'] }}">
                            {{ $item['badge']['text'] }}
                        </span>
                    @endisset
                </a>

                {{-- Submenú recursivo SOLO con hijos visibles --}}
                @include('partials.sidebar-menu', [
                    'items'    => $childItems,
                    'level'    => $level + 1,
                    'parentId' => $item['id'],
                ])
            </li>

            {{-- 3) Item simple (hoja) que pasa permisos --}}
        @elseif(passesAuth($item) && !empty($item['route']))
            <li class="{{ $level === 0 ? 'no-sub' : '' }}{{ $activeClass }}">
                <a href="{{ route($item['route']) }}" class="{{ trim($activeClass) }}">
                    @isset($item['icon'])
                        <i class="{{ $item['icon'] }}"></i>
                    @endisset
                    {{ $item['title'] }}
                </a>
            </li>
        @endif

    @endforeach
</ul>
