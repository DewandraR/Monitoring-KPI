<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Monitoring KPI') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Three.js CDN --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>

<body class="font-sans text-gray-900 antialiased">

    <div class="min-h-screen flex flex-col md:flex-row antialiased bg-gray-100">

        {{-- PANEL KIRI – 3D INTERACTIVE BACKGROUND --}}
        <div
            class="hidden md:flex md:w-1/2 bg-gradient-to-br from-lime-500 via-emerald-700 to-green-900 items-center justify-center p-16 relative overflow-hidden shadow-2xl">

            {{-- 3D Canvas Container --}}
            <div id="three-canvas" class="absolute inset-0 w-full h-full"></div>

            {{-- Overlay Gradient --}}
            <div class="absolute inset-0 bg-gradient-to-br from-black/20 via-transparent to-black/30"></div>

            {{-- Animated Circles --}}
            <div class="absolute top-20 left-20 w-64 h-64 bg-white/5 rounded-full blur-3xl animate-pulse"
                style="animation-duration: 4s;"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-emerald-400/10 rounded-full blur-3xl animate-pulse"
                style="animation-duration: 6s; animation-delay: 1s;"></div>

            {{-- Content --}}
            <div class="relative z-10 max-w-xl w-full space-y-8">
                <div
                    class="px-10 py-12 rounded-3xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.7)] transform hover:scale-105 transition-transform duration-500">

                    {{-- Logo with Animation --}}
                    <div class="flex justify-center mb-6">
                        <div class="relative">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-lime-400 to-emerald-400 rounded-2xl blur-xl opacity-50 animate-pulse">
                            </div>
                            <div class="relative bg-white/20 p-4 rounded-2xl backdrop-blur-sm border border-white/30">
                                <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <h1 class="text-5xl font-extrabold text-white tracking-wide text-center mb-4 animate-fade-in">
                        Monitoring KPI
                    </h1>

                    <p class="text-xl text-white/90 text-center font-light">
                        Sistem Manajemen Kinerja Terpadu
                    </p>

                    {{-- Stats Cards --}}
                    <div class="grid grid-cols-3 gap-4 mt-8">
                        <div
                            class="text-center p-4 bg-white/10 rounded-xl backdrop-blur-sm hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-1">
                            <div class="text-3xl font-bold text-white">99%</div>
                            <div class="text-xs text-white/80 mt-1">Uptime</div>
                        </div>
                        <div
                            class="text-center p-4 bg-white/10 rounded-xl backdrop-blur-sm hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-1">
                            <div class="text-3xl font-bold text-white">24/7</div>
                            <div class="text-xs text-white/80 mt-1">Support</div>
                        </div>
                        <div
                            class="text-center p-4 bg-white/10 rounded-xl backdrop-blur-sm hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-1">
                            <div class="text-3xl font-bold text-white">100+</div>
                            <div class="text-xs text-white/80 mt-1">Users</div>
                        </div>
                    </div>
                </div>

                {{-- Features List with Icons --}}
                <div class="space-y-4">
                    <div
                        class="flex items-center space-x-4 text-white/90 bg-white/5 backdrop-blur-sm p-4 rounded-xl border border-white/10 hover:bg-white/10 transition-all duration-300 transform hover:translate-x-2">
                        <span class="text-3xl">📊</span>
                        <span class="font-medium">Automated Data Extraction System</span>
                    </div>
                    <div
                        class="flex items-center space-x-4 text-white/90 bg-white/5 backdrop-blur-sm p-4 rounded-xl border border-white/10 hover:bg-white/10 transition-all duration-300 transform hover:translate-x-2">
                        <span class="text-3xl">🎯</span>
                        <span class="font-medium">Process Data on Demand</span>
                    </div>
                    <div
                        class="flex items-center space-x-4 text-white/90 bg-white/5 backdrop-blur-sm p-4 rounded-xl border border-white/10 hover:bg-white/10 transition-all duration-300 transform hover:translate-x-2">
                        <span class="text-3xl">📈</span>
                        <span class="font-medium">Performance Monitoring</span>
                    </div>
                </div>
            </div>
        </div>
        {{-- /PANEL KIRI --}}

        {{-- PANEL KANAN – FORM AUTH (LOGIN/REGISTER) --}}
        <div class="w-full md:w-1/2 flex items-center justify-center p-6 sm:p-10 lg:p-16">
            {{ $slot }}
        </div>
    </div>

    {{-- Three.js Animation Script --}}
    <script>
        // Setup Scene
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, 1, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({
            alpha: true,
            antialias: true
        });

        const container = document.getElementById('three-canvas');
        const width = container.clientWidth;
        const height = container.clientHeight;

        renderer.setSize(width, height);
        renderer.setClearColor(0x000000, 0);
        container.appendChild(renderer.domElement);

        camera.position.z = 5;

        // Create Particles
        const particlesGeometry = new THREE.BufferGeometry();
        const particlesCount = 1500;
        const posArray = new Float32Array(particlesCount * 3);
        const colors = new Float32Array(particlesCount * 3);

        for (let i = 0; i < particlesCount * 3; i += 3) {
            posArray[i] = (Math.random() - 0.5) * 15;
            posArray[i + 1] = (Math.random() - 0.5) * 15;
            posArray[i + 2] = (Math.random() - 0.5) * 15;

            const colorChoice = Math.random();
            if (colorChoice < 0.33) {
                colors[i] = 0.2;
                colors[i + 1] = 0.8;
                colors[i + 2] = 0.2; // Lime
            } else if (colorChoice < 0.66) {
                colors[i] = 0.0;
                colors[i + 1] = 0.6;
                colors[i + 2] = 0.4; // Emerald
            } else {
                colors[i] = 1.0;
                colors[i + 1] = 1.0;
                colors[i + 2] = 1.0; // White
            }
        }

        particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
        particlesGeometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

        const particlesMaterial = new THREE.PointsMaterial({
            size: 0.08,
            vertexColors: true,
            transparent: true,
            opacity: 0.8,
            blending: THREE.AdditiveBlending
        });

        const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
        scene.add(particlesMesh);

        // Create Rotating Rings
        const rings = [];
        for (let i = 0; i < 3; i++) {
            const ringGeometry = new THREE.TorusGeometry(2 + i * 0.5, 0.02, 16, 100);
            const ringMaterial = new THREE.MeshBasicMaterial({
                color: i === 0 ? 0x84cc16 : i === 1 ? 0x10b981 : 0x059669,
                transparent: true,
                opacity: 0.4
            });
            const ring = new THREE.Mesh(ringGeometry, ringMaterial);
            ring.rotation.x = Math.PI / 2 + i * 0.2;
            rings.push(ring);
            scene.add(ring);
        }

        // Create Floating Cubes
        const cubes = [];
        for (let i = 0; i < 8; i++) {
            const size = Math.random() * 0.3 + 0.1;
            const cubeGeometry = new THREE.BoxGeometry(size, size, size);
            const cubeMaterial = new THREE.MeshPhongMaterial({
                color: Math.random() > 0.5 ? 0x10b981 : 0x84cc16,
                transparent: true,
                opacity: 0.6,
                shininess: 100
            });
            const cube = new THREE.Mesh(cubeGeometry, cubeMaterial);
            cube.position.set(
                (Math.random() - 0.5) * 8,
                (Math.random() - 0.5) * 8,
                (Math.random() - 0.5) * 8
            );
            cubes.push(cube);
            scene.add(cube);
        }

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
        scene.add(ambientLight);

        const pointLight = new THREE.PointLight(0x10b981, 2, 100);
        pointLight.position.set(5, 5, 5);
        scene.add(pointLight);

        // Mouse Movement
        let mouseX = 0;
        let mouseY = 0;

        window.addEventListener('mousemove', (e) => {
            mouseX = (e.clientX / window.innerWidth) * 2 - 1;
            mouseY = -(e.clientY / window.innerHeight) * 2 + 1;
        });

        // Animation Loop
        let time = 0;

        function animate() {
            requestAnimationFrame(animate);
            time += 0.01;

            // Rotate particles
            particlesMesh.rotation.y = time * 0.1;
            particlesMesh.rotation.x = Math.sin(time * 0.2) * 0.1;

            // Animate rings
            rings.forEach((ring, i) => {
                ring.rotation.z = time * (0.5 + i * 0.2);
                ring.rotation.y = Math.sin(time + i) * 0.3;
            });

            // Animate cubes
            cubes.forEach((cube, i) => {
                cube.rotation.x += 0.01 + i * 0.001;
                cube.rotation.y += 0.01 + i * 0.001;
                cube.position.y += Math.sin(time * 2 + i) * 0.002;
            });

            // Camera movement based on mouse
            camera.position.x += (mouseX * 0.5 - camera.position.x) * 0.05;
            camera.position.y += (mouseY * 0.5 - camera.position.y) * 0.05;
            camera.lookAt(scene.position);

            renderer.render(scene, camera);
        }

        animate();

        // Handle window resize
        window.addEventListener('resize', () => {
            const width = container.clientWidth;
            const height = container.clientHeight;
            renderer.setSize(width, height);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        });
    </script>

    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.6s ease-out;
        }
    </style>

</body>

</html>
