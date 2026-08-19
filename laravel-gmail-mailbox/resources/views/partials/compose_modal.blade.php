<div class="modal fade" id="gmail_compose_modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 10px;">
            <div class="modal-header py-3 bg-light" style="border-top-left-radius: 10px; border-top-right-radius: 10px;">
                <h6 class="modal-title fw-bold text-dark mb-0" id="composeModalTitle">New Message</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="gmailComposeForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="reply_message_id" id="compose_reply_message_id" value="">
                <input type="hidden" name="threadId" id="compose_thread_id" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">TO</label>
                        <input type="email" name="to" id="compose_to" class="form-control" required placeholder="recipient@example.com">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">CC (Optional)</label>
                            <input type="text" name="cc" id="compose_cc" class="form-control" placeholder="cc@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">BCC (Optional)</label>
                            <input type="text" name="bcc" id="compose_bcc" class="form-control" placeholder="bcc@example.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">SUBJECT</label>
                        <input type="text" name="subject" id="compose_subject" class="form-control" required placeholder="Email subject">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">MESSAGE</label>
                        <textarea name="body" id="compose_body" class="form-control" rows="8" required placeholder="Write your message here..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold">ATTACHMENTS</label>
                        <input type="file" name="attachments[]" id="compose_attachments" class="form-control" multiple>
                    </div>
                </div>

                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center gap-1" id="btnSendMail">
                        <i class="mdi mdi-send"></i>
                        <span>Send Email</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openMailModal(to = '', subject = '', replyMessageId = '', threadId = '') {
        const form = document.getElementById('gmailComposeForm');
        form.reset();

        document.getElementById('compose_to').value = to;
        document.getElementById('compose_subject').value = subject;
        document.getElementById('compose_reply_message_id').value = replyMessageId;
        document.getElementById('compose_thread_id').value = threadId;

        document.getElementById('composeModalTitle').innerText = replyMessageId ? 'Reply Message' : 'New Message';

        const modal = new bootstrap.Modal(document.getElementById('gmail_compose_modal'));
        modal.show();
    }

    document.getElementById('gmailComposeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const sendBtn = document.getElementById('btnSendMail');
        const replyId = document.getElementById('compose_reply_message_id').value;
        const url = replyId ? `/${routePrefix}/reply/${replyId}` : `/${routePrefix}/send`;

        sendBtn.disabled = true;
        sendBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Sending...`;

        const formData = new FormData(this);

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            sendBtn.disabled = false;
            sendBtn.innerHTML = `<i class="mdi mdi-send"></i> <span>Send Email</span>`;

            if (data.status) {
                bootstrap.Modal.getInstance(document.getElementById('gmail_compose_modal')).hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Sent!', text: data.message, timer: 2000, showConfirmButton: false });
                } else {
                    alert(data.message);
                }
                fetchSearchResults();
            } else {
                alert(data.message || 'Failed to send email.');
            }
        })
        .catch(err => {
            sendBtn.disabled = false;
            sendBtn.innerHTML = `<i class="mdi mdi-send"></i> <span>Send Email</span>`;
            alert('An unexpected error occurred.');
        });
    });
</script>
