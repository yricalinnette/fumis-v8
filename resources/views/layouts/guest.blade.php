<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>FUMS | DOH-EVCHD</title>

        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="icon" type="image/x-icon" href="{{ asset('images/doh_logo.jpg') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body { 
                font-family: 'Inter', sans-serif; 
                background: radial-gradient(circle at top left, #f8fafc, #e2e8f0);
            }
            .glass-card {
                background: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                border: 1px solid rgba(255, 255, 255, 0.6);
            }
            .doh-gradient-bar {
                background: linear-gradient(90deg, #004a99 0%, #00843d 100%);
            }
            .btn-premium {
                background: linear-gradient(135deg, #004a99 0%, #006bbd 100%);
                color: white;
                font-weight: 700;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0, 74, 153, 0.2);
                border-radius: 12px;
            }
            .btn-premium:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(0, 74, 153, 0.3);
                filter: brightness(1.1);
            }
            .glass-input {
                background: rgba(255, 255, 255, 0.6) !important;
                border: 1px solid rgba(203, 213, 225, 0.8) !important;
                border-radius: 12px !important;
                transition: all 0.2s ease !important;
            }
            .glass-input:focus {
                background: #fff !important;
                border-color: #004a99 !important;
                box-shadow: 0 0 0 4px rgba(0, 74, 153, 0.05) !important;
                outline: none !important;
            }
            .glass-label {
                font-size: 0.7rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #475569;
                margin-bottom: 0.4rem;
                display: block;
            }
        </style>
    </head>
    <body class="antialiased text-slate-800">
        <div class="min-h-screen flex flex-col items-center justify-center p-6">
            
            <div class="w-full sm:max-w-[450px] glass-card shadow-[0_32px_64px_-15px_rgba(0,0,0,0.1)] rounded-[2.5rem] overflow-hidden">
                <div class="h-2 w-full doh-gradient-bar"></div>

                <div class="px-8 pt-10 pb-12">
                    <div class="text-center mb-10">
                        <div class="inline-block p-2 bg-white rounded-full shadow-sm mb-4">
                            <img src="{{ asset('images/doh_logo.jpg') }}" 
                                 alt="DOH Seal" 
                                 class="w-20 h-20 object-contain">
                        </div>
                        
                        <h1 class="text-4xl font-black tracking-tighter text-slate-900 italic leading-none">
                            FUMS
                        </h1>
                        <p class="mt-2 text-[10px] font-bold text-slate-500 uppercase tracking-[0.3em]">
                            Fund Utilization Monitoring System
                        </p>
                        
                        <div class="mt-6 flex items-center justify-center space-x-2">
                            <span class="h-px w-8 bg-slate-200"></span>
                            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">Authorized Access</span>
                            <span class="h-px w-8 bg-slate-200"></span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        {{ $slot }}
                    </div>

                    <div class="mt-10 pt-6 border-t border-slate-100 text-center">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            DOH-EVCHD FUMS v1.0
                        </p>
                    </div>
                </div>
            </div>

            <footer class="mt-12 text-center text-gray-400">
                <p class="text-[11px] font-semibold uppercase tracking-widest">
                    Department of Health — Eastern Visayas 
                </p>
                <p class="text-[10px] mt-1"> Center for Health Development</p>
            </footer>
        </div>
    </body>
</html>