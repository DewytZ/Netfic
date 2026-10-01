document.addEventListener('DOMContentLoaded', () => {
    const activeCard = document.querySelector('.auth-card');

    if (activeCard) {
        // 1. Ocultar dinámicamente para evitar parpadeos (FOUC)
        gsap.set(['.logo-text', activeCard, '.input-group', '.btn-primary', '.auth-options', '.auth-switch', '.rules'], {
            opacity: 0
        });

        // 2. Timeline elástica y suave
        const tl = gsap.timeline({ defaults: { ease: 'power4.out', duration: 0.8 } });

        tl.fromTo('.logo-text', 
            { y: -20, opacity: 0 }, 
            { y: 0, opacity: 1, duration: 0.5 }
        )
        .fromTo(activeCard, 
            { y: 35, opacity: 0, scale: 0.97 }, 
            { y: 0, opacity: 1, scale: 1, duration: 0.75, ease: 'back.out(1.1)' },
            "-=0.2"
        )
        .fromTo('.input-group', 
            { y: 15, opacity: 0 }, 
            { y: 0, opacity: 1, stagger: 0.08 }, 
            "-=0.35"
        )
        .fromTo(['.btn-primary', '.auth-options', '.auth-switch', '.rules'], 
            { y: 10, opacity: 0 }, 
            { y: 0, opacity: 1, stagger: 0.05 }, 
            "-=0.3"
        );

        // 3. Microinteracción táctil al enviar formulario
        const form = activeCard.querySelector('form');
        const submitBtn = activeCard.querySelector('.btn-primary');

        if (form && submitBtn) {
            form.addEventListener('submit', () => {
                gsap.to(submitBtn, {
                    scale: 0.97,
                    duration: 0.1,
                    yoyo: true,
                    repeat: 1
                });
            });
        }
    }
});