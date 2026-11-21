(() => {
  document.querySelectorAll('input[type="file"][capture][required]').forEach(input => {
    const form = input.closest('form');
    if (!form) return;
    const box = document.createElement('div');
    box.className = 'live-attendance-camera';
    box.innerHTML = '<video autoplay muted playsinline aria-label="Live camera preview"></video><img hidden alt="Captured attendance photo"><p role="status" aria-live="polite"></p><button type="button" data-start>Open camera</button> <button type="button" data-capture hidden>Capture photo</button> <button type="button" data-retake hidden>Retake</button>';
    const video = box.querySelector('video'), preview = box.querySelector('img');
    const status = box.querySelector('p'), start = box.querySelector('[data-start]');
    const capture = box.querySelector('[data-capture]'), retake = box.querySelector('[data-retake]');
    let stream, photoUrl, generation = 0;
    const stop = () => { generation++; stream?.getTracks().forEach(track => track.stop()); stream = null; video.srcObject = null; };
    const clear = () => { input.value = ''; if (photoUrl) URL.revokeObjectURL(photoUrl); photoUrl = null; preview.hidden = true; };
    async function open() {
      stop(); clear(); capture.hidden = true; retake.hidden = true;
      const attempt = generation;
      start.disabled = true;
      try {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('unavailable');
        const media = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 1280}}, audio: false});
        if (attempt !== generation) { media.getTracks().forEach(track => track.stop()); return; }
        stream = media; video.srcObject = stream; video.hidden = false;
        await video.play(); capture.hidden = false; status.textContent = 'Position your face, then capture your photo.';
      } catch (error) {
        stop(); status.textContent = error.name === 'NotAllowedError' ? 'Camera permission was denied. Allow camera access in your browser and try again.' : 'Camera unavailable. Connect a camera and use HTTPS, then try again.';
      } finally { start.disabled = false; }
    }
    start.addEventListener('click', open); retake.addEventListener('click', open);
    capture.addEventListener('click', async () => {
      if (!stream || !video.videoWidth) { status.textContent = 'Wait for the camera preview.'; return; }
      const attempt = generation;
      capture.disabled = true;
      try {
        const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        if (attempt !== generation) return;
        if (!blob || blob.size > 5 * 1024 * 1024) throw new Error('capture');
        const transfer = new DataTransfer(); transfer.items.add(new File([blob], 'attendance-camera.jpg', {type: 'image/jpeg'}));
        input.files = transfer.files; photoUrl = URL.createObjectURL(blob); preview.src = photoUrl; preview.hidden = false;
        stop(); video.hidden = true; capture.hidden = true; retake.hidden = false; status.textContent = 'Photo captured. You can now submit your attendance.';
      } catch { status.textContent = 'Could not capture the photo. Please try again.'; }
      finally { capture.disabled = false; }
    });
    input.required = false; input.hidden = true; input.closest('.camera-input')?.classList.add('live-camera-trigger');
    input.parentElement.insertAdjacentElement('afterend', box);
    form.addEventListener('submit', event => {
      if (!input.files.length) { event.preventDefault(); status.textContent = 'Capture a photo before submitting attendance.'; start.focus(); }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) { stop(); capture.hidden = true; } });
    window.addEventListener('pagehide', stop);
  });
})();
