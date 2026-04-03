
    <div class="app-wrapper d-block">
        <div class="">
            <!-- Body main section starts -->
            <main class="w-100 p-0">
                <!-- Login to your Account start -->
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12 p-0">
                            <div class="login-form-container">
                                <div class="mb-4 text-center">
                                    <a class="logo-home d-inline-block" href="#">

                                    </a>
                                    <h4 class="slogan" >IN GOD WE TRUST</h4>
                                </div>
                                <div class="form_container">
                                    <form wire:submit.prevent="authenticate" class="app-form">
                                        @csrf
                                        <div class="mb-3">
                                            <input  wire:model.defer="username" placeholder="Usuario" id="username"  type="text" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <input wire:model.defer="password" placeholder="Contraseña" id="password" type="password" class="form-control">
                                        </div>
                                        <!--div class="mb-3 form-check">
                                            <input wire:model="remember" type="checkbox" class="form-check-input" >
                                            <label class="form-check-label" for="formCheck1">Mantener sesión</label>
                                        </div-->

                                        @error('username') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror

                                        <div class="text-center">
                                            <button type="submit" role="button" class="btn btn-danger w-64">Ingresar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Login to your Account end -->
            </main>
            <!-- Body main section ends -->
        </div>
    </div>

