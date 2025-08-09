// Client-side fingerprinting script
class FingerprintCollector {
    constructor() {
        this.fingerprintData = {};
        this.fingerprintId = this.getFingerprintId();
    }

    // Generate or retrieve a persistent fingerprint ID
    getFingerprintId() {
        let id = localStorage.getItem('fp_id');
        if (!id) {
            id = 'fp_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('fp_id', id);
            // Set cookie that will expire in 1 year
            const expires = new Date();
            expires.setFullYear(expires.getFullYear() + 1);
            document.cookie = `fp_id=${id};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
        }
        return id;
    }

    // Collect browser and device information
    async collect() {
        this.collectScreenInfo();
        this.collectBrowserInfo();
        await this.collectWebGLInfo();
        await this.collectCanvasFingerprint();
        await this.collectAudioFingerprint();
        this.collectFonts();
        
        // Add the fingerprint ID to the data
        this.fingerprintData.fingerprint_id = this.fingerprintId;
        
        return this.fingerprintData;
    }

    collectScreenInfo() {
        this.fingerprintData.screen_resolution = `${window.screen.width}x${window.screen.height}`;
        this.fingerprintData.color_depth = window.screen.colorDepth;
        this.fingerprintData.pixel_ratio = window.devicePixelRatio || 1;
        this.fingerprintData.hardware_concurrency = navigator.hardwareConcurrency || 'unknown';
        this.fingerprintData.device_memory = navigator.deviceMemory || 'unknown';
        this.fingerprintData.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    }

    collectBrowserInfo() {
        this.fingerprintData.language = navigator.language;
        this.fingerprintData.languages = navigator.languages ? navigator.languages.join(',') : '';
        this.fingerprintData.do_not_track = navigator.doNotTrack === '1';
        this.fingerprintData.online = navigator.onLine;
        this.fingerprintData.cookie_enabled = navigator.cookieEnabled;
    }

    async collectWebGLInfo() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    this.fingerprintData.webgl_vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                    this.fingerprintData.webgl_renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                }
                
                // Get WebGL fingerprint
                const vertices = new Float32Array([-0.2, -0.9, 0, 0.4, -0.26, 0, 0, 0.733, 0]);
                const vertexBuffer = gl.createBuffer();
                gl.bindBuffer(gl.ARRAY_BUFFER, vertexBuffer);
                gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW);
                
                const vs = `attribute vec2 position;void main(){gl_Position=vec4(position,0,1);}`;
                const fs = `void main(void){gl_FragColor=vec4(0.0,0.0,0.0,1.0);}`;
                
                const program = this.compileShader(gl, vs, fs);
                gl.useProgram(program);
                
                const positionLocation = gl.getAttribLocation(program, 'position');
                gl.enableVertexAttribArray(positionLocation);
                gl.vertexAttribPointer(positionLocation, 3, gl.FLOAT, false, 0, 0);
                
                gl.drawArrays(gl.TRIANGLES, 0, 3);
                
                // Get the WebGL fingerprint
                const pixels = new Uint8Array(4);
                gl.readPixels(0, 0, 1, 1, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
                this.fingerprintData.webgl_fp = Array.from(pixels).join(',');
                
                // Clean up
                gl.deleteBuffer(vertexBuffer);
                gl.deleteProgram(program);
            }
        } catch (e) {
            console.error('WebGL error:', e);
        }
    }

    compileShader(gl, vsSource, fsSource) {
        const vertexShader = this.loadShader(gl, gl.VERTEX_SHADER, vsSource);
        const fragmentShader = this.loadShader(gl, gl.FRAGMENT_SHADER, fsSource);
        
        const shaderProgram = gl.createProgram();
        gl.attachShader(shaderProgram, vertexShader);
        gl.attachShader(shaderProgram, fragmentShader);
        gl.linkProgram(shaderProgram);
        
        if (!gl.getProgramParameter(shaderProgram, gl.LINK_STATUS)) {
            console.error('Unable to initialize the shader program: ' + gl.getProgramInfoLog(shaderProgram));
            return null;
        }
        
        return shaderProgram;
    }
    
    loadShader(gl, type, source) {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.error('An error occurred compiling the shaders: ' + gl.getShaderInfoLog(shader));
            gl.deleteShader(shader);
            return null;
        }
        
        return shader;
    }

    async collectCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 30;
            const ctx = canvas.getContext('2d');
            
            // Draw text with various settings
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(0, 0, 10, 20);
            
            ctx.fillStyle = '#069';
            ctx.fillText('Fingerprint', 15, 8);
            
            // Add some effects
            ctx.strokeStyle = 'rgba(102, 200, 0, 0.7)';
            ctx.strokeRect(0, 0, 200, 30);
            
            // Get the canvas fingerprint
            this.fingerprintData.canvas_fp = canvas.toDataURL().substring(22);
        } catch (e) {
            console.error('Canvas error:', e);
        }
    }

    async collectAudioFingerprint() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const analyser = audioContext.createAnalyser();
            
            oscillator.type = 'triangle';
            oscillator.frequency.setValueAtTime(1000, audioContext.currentTime);
            
            oscillator.connect(analyser);
            analyser.connect(audioContext.destination);
            
            oscillator.start(0);
            
            // Get audio fingerprint
            const dataArray = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteFrequencyData(dataArray);
            
            this.fingerprintData.audio_fp = Array.from(dataArray).join(',');
            
            // Clean up
            oscillator.stop();
            audioContext.close();
        } catch (e) {
            console.error('Audio error:', e);
        }
    }

    collectFonts() {
        try {
            // Common fonts to test
            const fonts = [
                'Arial', 'Arial Black', 'Arial Narrow', 'Arial Rounded MT Bold', 'Arial Unicode MS',
                'Calibri', 'Cambria', 'Candara', 'Century Gothic', 'Comic Sans MS', 'Consolas',
                'Courier New', 'DejaVu Sans', 'Franklin Gothic Medium', 'Garamond', 'Georgia',
                'Helvetica', 'Impact', 'Lucida Console', 'Lucida Sans Unicode', 'Microsoft Sans Serif',
                'Palatino Linotype', 'Segoe UI', 'Tahoma', 'Times New Roman', 'Trebuchet MS',
                'Verdana', 'Webdings', 'Wingdings', 'Andale Mono', 'Baskerville', 'Book Antiqua',
                'Brush Script MT', 'Copperplate', 'Didot', 'Futura', 'Gill Sans', 'Goudy Old Style',
                'Hoefler Text', 'Lucida Bright', 'Optima', 'Rockwell', 'Segoe Print', 'Segoe Script',
                'Snell Roundhand', 'Vivaldi', 'Zapfino'
            ];
            
            // Check which fonts are available
            const availableFonts = [];
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            const text = 'abcdefghijklmnopqrstuvwxyz0123456789';
            
            // Get the default font
            context.font = '72px monospace';
            const defaultWidth = context.measureText(text).width;
            
            // Check each font
            for (const font of fonts) {
                context.font = `72px "${font}", monospace`;
                if (context.measureText(text).width !== defaultWidth) {
                    availableFonts.push(font);
                }
            }
            
            this.fingerprintData.fonts = availableFonts.join(',');
        } catch (e) {
            console.error('Font detection error:', e);
        }
    }

    // Send fingerprint data to the server
    async sendToServer() {
        try {
            await this.collect();
            
            // Send the data to the server
            const response = await fetch('/api/fingerprint', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(this.fingerprintData)
            });
            
            if (!response.ok) {
                throw new Error('Failed to send fingerprint data');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error sending fingerprint data:', error);
            throw error;
        }
    }
}

// Initialize and send fingerprint data when the page loads
document.addEventListener('DOMContentLoaded', () => {
    // Only run on registration page
    if (window.location.pathname.includes('/register')) {
        const fingerprintCollector = new FingerprintCollector();
        
        // Send fingerprint data when the form is submitted
        const form = document.querySelector('form[action$="/register"]');
        if (form) {
            form.addEventListener('submit', async (e) => {
                try {
                    // Add a hidden field with the fingerprint ID
                    let fingerprintField = form.querySelector('input[name="fingerprint_id"]');
                    if (!fingerprintField) {
                        fingerprintField = document.createElement('input');
                        fingerprintField.type = 'hidden';
                        fingerprintField.name = 'fingerprint_id';
                        form.appendChild(fingerprintField);
                    }
                    fingerprintField.value = fingerprintCollector.fingerprintId;
                    
                    // Send the full fingerprint data in the background
                    fingerprintCollector.sendToServer().catch(console.error);
                } catch (error) {
                    console.error('Error collecting fingerprint:', error);
                }
            });
        }
    }
});
