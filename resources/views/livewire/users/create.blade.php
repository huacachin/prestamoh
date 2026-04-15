<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6"><h4 class="main-title title-modules">AGREGAR USUARIO</h4></div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-settings f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Configuración</span></a>
                </li>
                <li class="d-flex"><a href="{{ route('settings.users.index') }}" class="f-s-14">Usuarios</a></li>
                <li class="d-flex active"><a class="f-s-14">Agregar</a></li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="perm-grid form-two-cols">
                <div class="perm-row">
                    <div class="perm-col-title">Nombre</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar nombre" wire:model.live="name">
                        @error('name') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row">
                    <div class="perm-col-title">Usuario</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar usuario" wire:model="username">
                        @error('username') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row" x-data="{
                    show: false,
                    strength: 0, strengthLabel: '', strengthColor: '',
                    generate() {
                        const u='ABCDEFGHIJKLMNOPQRSTUVWXYZ', l='abcdefghijklmnopqrstuvwxyz', n='0123456789', s='!@#$%^&*_+-=';
                        const all=u+l+n+s; let p=[u[Math.floor(Math.random()*u.length)],l[Math.floor(Math.random()*l.length)],n[Math.floor(Math.random()*n.length)],s[Math.floor(Math.random()*s.length)]];
                        for(let i=4;i<14;i++) p.push(all[Math.floor(Math.random()*all.length)]);
                        p=p.sort(()=>Math.random()-0.5).join(''); $wire.set('pwd',p); this.show=true; this.checkStrength(p);
                    },
                    checkStrength(v){let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[a-z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[!@#$%^&*_+\-=]/.test(v))s++;this.strength=s;if(s<=2){this.strengthLabel='Débil';this.strengthColor='bg-danger'}else if(s<=3){this.strengthLabel='Media';this.strengthColor='bg-warning'}else{this.strengthLabel='Fuerte';this.strengthColor='bg-success'}}
                }" x-init="$watch('$wire.pwd', val => checkStrength(val || ''))">
                    <div class="perm-col-title">Contraseña</div>
                    <div class="perm-col-controls">
                        <div class="input-group input-group-sm">
                            <input :type="show ? 'text' : 'password'" class="form-control form-control-sm" placeholder="Ingresar contraseña" wire:model.live="pwd">
                            <button class="btn btn-outline-secondary" type="button" @click="show = !show"><i class="ti" :class="show ? 'ti-eye-off' : 'ti-eye'"></i></button>
                            <button class="btn btn-outline-primary" type="button" @click="generate()"><i class="ti ti-key"></i> Generar</button>
                        </div>
                        <div class="mt-1" x-show="$wire.pwd && $wire.pwd.length > 0" x-cloak>
                            <div class="progress" style="height: 5px;"><div class="progress-bar" :class="strengthColor" :style="'width:' + (strength * 20) + '%'"></div></div>
                            <small :class="strengthColor.replace('bg-','text-')" x-text="strengthLabel"></small>
                        </div>
                        @error('pwd') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row">
                    <div class="perm-col-title">Email</div>
                    <div class="perm-col-controls">
                        <input type="email" class="form-control form-control-sm" placeholder="Ingresar email" wire:model="email">
                        @error('email') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row">
                    <div class="perm-col-title">Tipo Documento</div>
                    <div class="perm-col-controls">
                        <select class="form-control form-control-sm" wire:model="document_type">
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="CE">CE</option>
                        </select>
                        @error('document_type') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row">
                    <div class="perm-col-title">N° Documento</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar número" wire:model="document_number">
                        @error('document_number') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row">
                    <div class="perm-col-title">Teléfono</div>
                    <div class="perm-col-controls">
                        <input type="text" class="form-control form-control-sm" placeholder="Ingresar teléfono" wire:model="phone">
                        @error('phone') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row">
                    <div class="perm-col-title">Sucursal</div>
                    <div class="perm-col-controls">
                        <select class="form-control form-control-sm" wire:model="headquarter_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($headquarters as $h)
                                <option value="{{ $h->id }}">{{ $h->name }}</option>
                            @endforeach
                        </select>
                        @error('headquarter_id') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="perm-row span-2">
                    <div class="perm-col-title">Rol</div>
                    <div class="perm-col-controls">
                        <div class="perm-chips">
                            @forelse($roles as $r)
                                <label class="chip-radio">
                                    <input type="radio" class="form-check-input" name="role_single_add" value="{{ $r->id }}" wire:model="selectedRoleId">
                                    <span>{{ ucfirst($r->name) }}</span>
                                </label>
                            @empty
                                <span class="text-warning small">No hay roles definidos.</span>
                            @endforelse
                        </div>
                        @error('selectedRoleId') <span class="title-modules small">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm" wire:click="save">Guardar</button>
                <button type="button" class="btn btn-danger btn-sm" wire:click="clean">Limpiar</button>
                <a class="btn btn-secondary btn-sm" href="{{ route('settings.users.index') }}">Volver</a>
            </div>
        </div>
    </div>
</div>
