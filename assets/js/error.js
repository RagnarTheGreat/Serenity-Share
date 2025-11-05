/**
 * Initializes the particle effect on error pages
 * @param {number} errorCode - The HTTP error code
 */
function initParticles(errorCode) {
    particlesJS("particles-js", {
        particles: {
            number: {
                value: 100,
                density: {
                    enable: true,
                    value_area: 1000
                }
            },
            color: {
                value: errorCode === 404 ? "#3b82f6" : "#f43f5e"
            },
            opacity: {
                value: 0.3,
                random: true,
                anim: {
                    enable: true,
                    speed: 1,
                    opacity_min: 0.1,
                    sync: false
                }
            },
            size: {
                value: 4,
                random: true
            },
            line_linked: {
                enable: true,
                distance: 150,
                color: errorCode === 404 ? "#3b82f6" : "#f43f5e",
                opacity: 0.2,
                width: 1
            },
            move: {
                enable: true,
                speed: 3,
                direction: "none",
                random: true,
                straight: false,
                out_mode: "out",
                bounce: false
            }
        },
        interactivity: {
            detect_on: "canvas",
            events: {
                onhover: {
                    enable: true,
                    mode: "grab"
                },
                resize: true
            },
            modes: {
                grab: {
                    distance: 140,
                    line_linked: {
                        opacity: 0.5
                    }
                }
            }
        }
    });
}

/**
 * Initialize when the page loads
 */
document.addEventListener("DOMContentLoaded", function() {
    const errorCode = parseInt(document.querySelector('meta[name="error-code"]').getAttribute("content"));
    initParticles(errorCode);
});
