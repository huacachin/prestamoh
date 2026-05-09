<div x-data="commandPalette()" x-cloak>

    {{-- Backdrop + Modal --}}
    <div x-show="open"
         x-transition.opacity.duration.150ms
         @click.self="close()"
         @keydown.escape.window="close()"
         class="cmdk-backdrop">

        <div class="cmdk-modal" @click.stop x-trap.inert.noscroll="open">

            {{-- Search bar --}}
            <div class="cmdk-search">
                <i class="ti ti-search cmdk-search__icon"></i>
                <input type="text"
                       x-ref="searchInput"
                       wire:model.live.debounce.250ms="query"
                       @keydown.arrow-down.prevent="moveDown()"
                       @keydown.arrow-up.prevent="moveUp()"
                       @keydown.enter.prevent="activate()"
                       placeholder="Buscar clientes, créditos, navegar… (cmd+k)"
                       autocomplete="off">
                <kbd class="cmdk-kbd">esc</kbd>
            </div>

            {{-- Loading mini --}}
            <div wire:loading wire:target="query" class="cmdk-loading">
                <i class="ti ti-loader-2 spin"></i> Buscando…
            </div>

            <div class="cmdk-results" wire:loading.remove wire:target="query">

                @php
                    $items = [];
                    foreach ($clients as $c)  $items[] = ['kind'=>'client',  'data'=>$c];
                    foreach ($credits as $c)  $items[] = ['kind'=>'credit',  'data'=>$c];
                    foreach ($nav as $n)      $items[] = ['kind'=>'nav',     'data'=>$n];
                    foreach ($actions as $a)  $items[] = ['kind'=>'action',  'data'=>$a];
                @endphp

                <span x-init="setItemsCount({{ count($items) }})"
                      x-data="{ n: {{ count($items) }} }"
                      x-effect="setItemsCount(n)"
                      style="display:none;"></span>

                @if(count($items) === 0)
                    <div class="cmdk-empty">
                        <i class="ti ti-search-off"></i>
                        @if($query !== '')
                            <p>Sin resultados para <strong>"{{ $query }}"</strong></p>
                        @else
                            <p>Comenzá a tipear para buscar…</p>
                        @endif
                    </div>
                @endif

                @php $idx = -1; @endphp

                @if(count($clients))
                    <div class="cmdk-group">CLIENTES</div>
                    @foreach($clients as $c)
                        @php $idx++; @endphp
                        <a href="{{ $c['url'] }}" class="cmdk-item"
                           :class="selectedIndex === {{ $idx }} ? 'cmdk-item--active' : ''"
                           @mouseenter="selectedIndex = {{ $idx }}">
                            <i class="ti {{ $c['icon'] }} cmdk-item__icon"></i>
                            <div class="cmdk-item__main">
                                <div class="cmdk-item__title">{{ $c['title'] }}</div>
                                <div class="cmdk-item__sub">{{ $c['sub'] }}</div>
                            </div>
                        </a>
                    @endforeach
                @endif

                @if(count($credits))
                    <div class="cmdk-group">CRÉDITOS</div>
                    @foreach($credits as $c)
                        @php $idx++; @endphp
                        <a href="{{ $c['url'] }}" class="cmdk-item"
                           :class="selectedIndex === {{ $idx }} ? 'cmdk-item--active' : ''"
                           @mouseenter="selectedIndex = {{ $idx }}">
                            <i class="ti {{ $c['icon'] }} cmdk-item__icon"></i>
                            <div class="cmdk-item__main">
                                <div class="cmdk-item__title">{{ $c['title'] }}</div>
                                <div class="cmdk-item__sub">{{ $c['sub'] }}</div>
                            </div>
                        </a>
                    @endforeach
                @endif

                @if(count($nav))
                    <div class="cmdk-group">IR A…</div>
                    @foreach($nav as $n)
                        @php $idx++; @endphp
                        <a href="{{ $n['url'] }}" class="cmdk-item"
                           :class="selectedIndex === {{ $idx }} ? 'cmdk-item--active' : ''"
                           @mouseenter="selectedIndex = {{ $idx }}">
                            <i class="ti {{ $n['icon'] }} cmdk-item__icon"></i>
                            <div class="cmdk-item__main">
                                <div class="cmdk-item__title">{{ $n['title'] }}</div>
                            </div>
                            @if($n['sub'])
                                <kbd class="cmdk-kbd cmdk-kbd--small">{{ $n['sub'] }}</kbd>
                            @endif
                        </a>
                    @endforeach
                @endif

                @if(count($actions))
                    <div class="cmdk-group">ACCIONES</div>
                    @foreach($actions as $a)
                        @php $idx++; @endphp
                        <a href="{{ $a['url'] }}" class="cmdk-item"
                           :class="selectedIndex === {{ $idx }} ? 'cmdk-item--active' : ''"
                           @mouseenter="selectedIndex = {{ $idx }}">
                            <i class="ti {{ $a['icon'] }} cmdk-item__icon"></i>
                            <div class="cmdk-item__main">
                                <div class="cmdk-item__title">{{ $a['title'] }}</div>
                            </div>
                            @if($a['sub'])
                                <kbd class="cmdk-kbd cmdk-kbd--small">{{ $a['sub'] }}</kbd>
                            @endif
                        </a>
                    @endforeach
                @endif
            </div>

            {{-- Footer --}}
            <div class="cmdk-footer">
                <span>
                    <kbd class="cmdk-kbd"><i class="ti ti-arrow-up"></i></kbd>
                    <kbd class="cmdk-kbd"><i class="ti ti-arrow-down"></i></kbd>
                    navegar
                </span>
                <span>
                    <kbd class="cmdk-kbd"><i class="ti ti-corner-down-left"></i></kbd>
                    abrir
                </span>
                <span>
                    <kbd class="cmdk-kbd">esc</kbd>
                    cerrar
                </span>
                <span class="ms-auto">
                    <kbd class="cmdk-kbd">?</kbd>
                    atajos
                </span>
            </div>
        </div>
    </div>

    {{-- Modal de Atajos (?) --}}
    <div x-show="helpOpen"
         x-transition.opacity.duration.150ms
         @click.self="helpOpen = false"
         @keydown.escape.window="helpOpen = false"
         class="cmdk-backdrop">
        <div class="cmdk-modal cmdk-modal--help" @click.stop>
            <div class="cmdk-search" style="border-bottom:1px solid #e8eaf0;">
                <i class="ti ti-keyboard cmdk-search__icon"></i>
                <strong style="font-size:14px;">Atajos de teclado</strong>
                <button type="button" @click="helpOpen=false" class="cmdk-kbd" style="cursor:pointer;">esc</button>
            </div>
            <div class="cmdk-help-grid">
                <div class="cmdk-help-section">
                    <h6>Paleta</h6>
                    <div class="cmdk-help-row"><kbd>cmd</kbd> <kbd>k</kbd> <span>Abrir paleta de comandos</span></div>
                    <div class="cmdk-help-row"><kbd>?</kbd> <span>Mostrar atajos</span></div>
                </div>
                <div class="cmdk-help-section">
                    <h6>Navegar</h6>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>d</kbd> <span>Dashboard</span></div>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>c</kbd> <span>Clientes</span></div>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>r</kbd> <span>Préstamos</span></div>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>p</kbd> <span>Pagos</span></div>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>i</kbd> <span>Ingresos</span></div>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>e</kbd> <span>Egresos</span></div>
                    <div class="cmdk-help-row"><kbd>g</kbd> <kbd>a</kbd> <span>Apertura caja</span></div>
                </div>
                <div class="cmdk-help-section">
                    <h6>Crear</h6>
                    <div class="cmdk-help-row"><kbd>n</kbd> <kbd>c</kbd> <span>Nuevo cliente</span></div>
                    <div class="cmdk-help-row"><kbd>n</kbd> <kbd>r</kbd> <span>Nuevo préstamo</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function commandPalette() {
    return {
        open: false,
        helpOpen: false,
        selectedIndex: 0,
        itemsCount: 0,
        comboBuffer: '',
        comboTimer: null,

        init() {
            // Listener global de teclado
            window.addEventListener('keydown', (e) => this.handleGlobalKey(e));

            // Foco al abrir
            this.$watch('open', (v) => {
                if (v) {
                    this.selectedIndex = 0;
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            });

            // Reset selección cuando llegan nuevos items
            window.addEventListener('cmdk-items-changed', () => {
                this.selectedIndex = 0;
            });
        },

        setItemsCount(n) {
            this.itemsCount = n;
            this.selectedIndex = Math.min(this.selectedIndex, Math.max(0, n - 1));
        },

        handleGlobalKey(e) {
            // Si se está tipeando en un input/textarea, ignorar excepto ESC y Cmd+K
            const tag = (e.target?.tagName || '').toLowerCase();
            const inField = ['input','textarea','select'].includes(tag) || e.target?.isContentEditable;

            // Cmd+K / Ctrl+K → abrir paleta
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.toggle();
                return;
            }

            if (this.open || this.helpOpen) return;
            if (inField) return;

            // ? → mostrar ayuda
            if (e.key === '?') {
                e.preventDefault();
                this.helpOpen = true;
                return;
            }

            // Combos g+letra y n+letra (estilo Gmail)
            const k = e.key.toLowerCase();
            if (this.comboBuffer === '') {
                if (k === 'g' || k === 'n') {
                    this.comboBuffer = k;
                    clearTimeout(this.comboTimer);
                    this.comboTimer = setTimeout(() => this.comboBuffer = '', 1200);
                    e.preventDefault();
                }
            } else {
                const combo = this.comboBuffer + k;
                this.comboBuffer = '';
                clearTimeout(this.comboTimer);
                const url = this.comboToUrl(combo);
                if (url) { window.location.href = url; e.preventDefault(); }
            }
        },

        comboToUrl(combo) {
            const map = {
                'gd': @json(auth()->user()?->can('dashboard') ? route('dashboard.index') : null),
                'gc': @json(auth()->user()?->can('clientes') ? route('clients.index') : null),
                'gr': @json(auth()->user()?->can('creditos') ? route('credits.index') : null),
                'gp': @json(auth()->user()?->can('pagos') ? route('payments.index') : null),
                'gi': @json(auth()->user()?->can('caja.ingresos') ? route('cash.incomes') : null),
                'ge': @json(auth()->user()?->can('caja.egresos') ? route('cash.expenses') : null),
                'ga': @json(auth()->user()?->can('caja.apertura') ? route('cash.opening') : null),
                'nc': @json(auth()->user()?->can('clientes') ? route('clients.create') : null),
                'nr': @json(auth()->user()?->can('creditos') ? route('credits.create') : null),
            };
            return map[combo] || null;
        },

        toggle() { this.open ? this.close() : this.openPalette(); },
        openPalette() { this.open = true; this.selectedIndex = 0; },
        close() { this.open = false; this.$wire.set('query', ''); },

        moveDown() { if (this.itemsCount > 0) this.selectedIndex = (this.selectedIndex + 1) % this.itemsCount; this.scrollIntoView(); },
        moveUp()   { if (this.itemsCount > 0) this.selectedIndex = (this.selectedIndex - 1 + this.itemsCount) % this.itemsCount; this.scrollIntoView(); },

        scrollIntoView() {
            this.$nextTick(() => {
                const el = document.querySelector('.cmdk-item--active');
                el?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        },

        activate() {
            const el = document.querySelectorAll('.cmdk-item')[this.selectedIndex];
            if (el?.href) window.location.href = el.href;
        },
    };
}
</script>

<style>
[x-cloak] { display: none !important; }

.cmdk-backdrop {
    position: fixed; inset: 0; z-index: 1090;
    background: rgba(11, 27, 61, 0.55);
    backdrop-filter: blur(4px);
    display: flex; align-items: flex-start; justify-content: center;
    padding-top: 14vh;
}
.cmdk-modal {
    background: #fff;
    width: 100%; max-width: 640px;
    border-radius: 14px;
    box-shadow: 0 24px 60px -10px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.05);
    display: flex; flex-direction: column;
    max-height: 70vh;
    overflow: hidden;
    font-family: 'Poppins', sans-serif;
}

.cmdk-search {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid #ecedf2;
}
.cmdk-search__icon { font-size: 18px; color: #6b7280; }
.cmdk-search input {
    flex: 1; border: 0; outline: 0; font-size: 14px;
    background: transparent; color: #0b1b3d;
    font-family: 'Poppins', sans-serif;
}

.cmdk-modal kbd, .cmdk-modal .cmdk-kbd {
    background: #f1f3f9 !important; color: #4b5563 !important;
    border: 1px solid #d8dce6 !important;
    border-bottom-width: 2px !important;
    border-radius: 4px !important;
    padding: 2px 7px !important;
    font-size: 11px !important;
    font-family: 'Poppins', sans-serif !important;
    font-weight: 600 !important;
    letter-spacing: 0.02em !important;
    line-height: 1.2 !important;
    display: inline-flex !important;
    align-items: center;
    min-width: 22px;
    justify-content: center;
    box-shadow: 0 1px 0 rgba(0,0,0,.04);
}
.cmdk-modal kbd i, .cmdk-modal .cmdk-kbd i {
    font-size: 12px; line-height: 1;
}
.cmdk-kbd--small { font-size: 9px !important; padding: 1px 5px !important; min-width: 18px; }

.cmdk-loading {
    padding: 24px; text-align: center; color: #6b7280; font-size: 13px;
}
.cmdk-loading .spin { display: inline-block; animation: cmdkSpin 1s linear infinite; }
@keyframes cmdkSpin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

.cmdk-results {
    overflow-y: auto; flex: 1; padding: 6px 0;
}
.cmdk-empty {
    padding: 36px 16px; text-align: center; color: #6b7280; font-size: 13px;
}
.cmdk-empty i { font-size: 32px; opacity: .35; display: block; margin-bottom: 6px; }
.cmdk-empty p { margin: 0; }

.cmdk-group {
    padding: 8px 16px 4px;
    font-size: 10px; letter-spacing: 0.16em; text-transform: uppercase;
    color: #6b7280; font-weight: 600;
}

.cmdk-item {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 16px;
    text-decoration: none; color: #0b1b3d;
    cursor: pointer; transition: background .1s ease;
}
.cmdk-item:hover, .cmdk-item--active {
    background: #eef6fc;
    color: #0b1b3d;
}
.cmdk-item__icon { font-size: 16px; color: #6b7280; flex-shrink: 0; width: 20px; text-align: center; }
.cmdk-item--active .cmdk-item__icon { color: #009BDC; }
.cmdk-item__main { flex: 1; min-width: 0; }
.cmdk-item__title {
    font-size: 13px; font-weight: 500;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cmdk-item__sub {
    font-size: 11px; color: #6b7280; margin-top: 1px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.cmdk-footer {
    display: flex; gap: 16px; align-items: center;
    padding: 10px 16px;
    border-top: 1px solid #ecedf2;
    background: #fafbfd;
    font-size: 11px; color: #4b5563; font-weight: 500;
}
.cmdk-footer span {
    display: inline-flex; align-items: center; gap: 4px;
}

/* Help modal */
.cmdk-modal--help { max-width: 720px; max-height: 80vh; }
.cmdk-help-grid {
    padding: 16px;
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px;
}
.cmdk-help-section h6 {
    font-size: 10px; letter-spacing: 0.16em; text-transform: uppercase;
    color: #6b7280; font-weight: 600; margin: 0 0 8px;
}
.cmdk-help-row {
    display: flex; align-items: center; gap: 6px;
    padding: 4px 0; font-size: 12px;
}
.cmdk-help-row kbd {
    background: #f1f3f9; border: 1px solid #d8dce6; border-bottom-width: 2px;
    border-radius: 3px; padding: 1px 6px; font-size: 10px;
    font-family: 'Poppins', sans-serif;
}
.cmdk-help-row span { color: #0b1b3d; margin-left: 4px; }

@media (max-width: 600px) {
    .cmdk-help-grid { grid-template-columns: 1fr; }
}
</style>
