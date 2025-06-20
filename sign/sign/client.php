<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen & Bathroom (NE) Ltd - Contract Review & Signing</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
            color: #333;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .contract-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 25px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        
        .section-title {
            color: #2563eb;
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-item strong {
            display: inline-block;
            width: 120px;
            color: #374151;
        }
        
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #059669;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .table th,
        .table td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
        }
        
        .table th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        
        .table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .payment-table th {
            background-color: #eff6ff;
            color: #1e40af;
        }
        
        .terms-content {
            max-height: 400px;
            overflow-y: auto;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #fafafa;
        }
        
        .terms-content h3 {
            color: #2563eb;
            margin-bottom: 15px;
        }
        
        .terms-content h4 {
            color: #374151;
            margin: 15px 0 8px 0;
        }
        
        .terms-content p {
            margin-bottom: 10px;
        }
        
        .signature-section {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
        }
        
        .checkbox-container {
            margin-bottom: 20px;
        }
        
        .checkbox-container input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 8px;
            transform: scale(1.2);
        }
        
        .input-field {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .signature-pad {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: crosshair;
            background: white;
            display: block;
            margin-bottom: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background-color: #059669;
            color: white;
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
        }
        
        .btn-primary:hover:not(:disabled) {
            background-color: #047857;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .btn-blue {
            background-color: #2563eb;
            color: white;
        }
        
        .btn-blue:hover {
            background-color: #1d4ed8;
        }
        
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 50vh;
            flex-direction: column;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e5e7eb;
            border-top: 5px solid #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-state {
            background-color: #fee2e2;
            border: 2px solid #fca5a5;
            color: #dc2626;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            max-width: 600px;
            margin: 50px auto;
        }
        
        .error-state h3 {
            font-size: 1.25rem;
            margin-bottom: 10px;
        }
        
        .success-state {
            background-color: #ecfdf5;
            border: 2px solid #a7f3d0;
            color: #065f46;
            text-align: center;
            padding: 30px;
        }
        
        .success-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background-color: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-icon::after {
            content: '✓';
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .success-state h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
            }
        }
        
        .hidden {
            display: none !important;
        }
        
        @media print {
            body { margin: 0; background: white; }
            .no-print { display: none !important; }
            .contract-section { box-shadow: none; border: 1px solid #000; }
            .signature-section { background: white; }
        }
    </style>
</head>
<body>
    <!-- Loading State -->
    <div id="loading-state" class="loading">
        <div class="spinner"></div>
        <p>Loading your contract...</p>
    </div>

    <!-- Error State -->
    <div id="error-state" class="hidden">
        <div class="error-state">
            <h3>Unable to Load Contract</h3>
            <p id="error-message">Please check your email for the correct contract link.</p>
        </div>
    </div>

    <!-- Main Contract Interface -->
    <div id="contract-interface" class="hidden">
        <div class="container">
            <!-- Header -->
            <div class="contract-section">
                <div class="header">
                    <h1>Kitchen & Bathroom (NE) Ltd</h1>
                    <p>Contract Review & Digital Signing</p>
                </div>
                
                <div class="grid">
                    <div>
                        <h3 class="section-title">Client Information</h3>
                        <div class="info-item"><strong>Name:</strong> <span id="client-name">-</span></div>
                        <div class="info-item"><strong>Email:</strong> <span id="client-email">-</span></div>
                        <div class="info-item"><strong>Phone:</strong> <span id="client-phone">-</span></div>
                        <div class="info-item"><strong>Address:</strong> <span id="client-address">-</span></div>
                    </div>
                    
                    <div>
                        <h3 class="section-title">Project Details</h3>
                        <div class="info-item"><strong>Quote Number:</strong> <span id="quote-number">-</span></div>
                        <div class="info-item"><strong>Project Type:</strong> <span id="project-type">-</span></div>
                        <div class="info-item"><strong>Installation:</strong> <span id="installation-date">-</span></div>
                        <div class="info-item"><strong>Total Amount:</strong> <span id="total-amount" class="total-amount">£0</span></div>
                    </div>
                </div>
            </div>

            <!-- Scope of Work -->
            <div class="contract-section">
                <h3 class="section-title">Scope of Work</h3>
                <div id="scope-of-work">-</div>
            </div>

            <!-- Line Items -->
            <div class="contract-section">
                <h3 class="section-title">Items & Services</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="line-items-body">
                        <!-- Line items will be populated here -->
                    </tbody>
                </table>
            </div>

            <!-- Payment Schedule -->
            <div class="contract-section">
                <h3 class="section-title">Payment Schedule</h3>
                <table class="table payment-table">
                    <thead>
                        <tr>
                            <th>Stage</th>
                            <th>Description</th>
                            <th>Percentage</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="payment-schedule-body">
                        <!-- Payment schedule will be populated here -->
                    </tbody>
                </table>
            </div>

            <!-- Terms and Conditions -->
            <div class="contract-section">
                <h3 class="section-title">Terms and Conditions</h3>
                <div id="terms-content" class="terms-content">
                    <!-- Terms content will be populated here -->
                </div>
            </div>

            <!-- Signature Section -->
            <div id="signature-section" class="contract-section signature-section no-print">
                <h3 class="section-title">Digital Signature</h3>
                
                <div class="checkbox-container">
                    <label>
                        <input type="checkbox" id="terms-agreement">
                        I have read and agree to the terms and conditions outlined above.
                    </label>
                </div>
                
                <div>
                    <strong>Signature Type:</strong>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="signature-type" value="typed" checked>
                            Type Name
                        </label>
                        <label>
                            <input type="radio" name="signature-type" value="drawn">
                            Draw Signature
                        </label>
                    </div>
                </div>
                
                <!-- Typed Signature -->
                <div id="typed-signature">
                    <label><strong>Type your full name:</strong></label>
                    <input type="text" id="typed-name" class="input-field" placeholder="Enter your full name">
                </div>
                
                <!-- Drawn Signature -->
                <div id="drawn-signature" class="hidden">
                    <label><strong>Draw your signature:</strong></label>
                    <canvas id="signature-canvas" class="signature-pad" width="500" height="150"></canvas>
                    <button type="button" id="clear-signature" class="btn btn-secondary">Clear Signature</button>
                </div>
                
                <button id="sign-contract" class="btn btn-primary" disabled>
                    Sign Contract
                </button>
            </div>

            <!-- Signed State -->
            <div id="signed-state" class="hidden contract-section success-state">
                <div class="success-icon"></div>
                <h3>Contract Successfully Signed!</h3>
                <p>Thank you for signing your contract. You will receive a confirmation email shortly.</p>
                <p><strong>Signed on:</strong> <span id="signed-date"></span></p>
                
                   <div class="button-group">
                    <button onclick="downloadContract()" class="btn btn-blue">
                        📄 Download Contract
                    </button>
                    <button onclick="printContract()" class="btn btn-secondary">
                        🖨️ Print Contract
                    </button>
                    <button onclick="printTermsOnly()" class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; margin: 0 5px;">
                        📋 Print Terms & Conditions
                    </button>
                    <button onclick="downloadTermsOnly()" class="btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; margin: 0 5px;">
                        📥 Download Terms & Conditions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let contract = null;
        let currentToken = null;
        let signaturePadCanvas = null;
        let signaturePadContext = null;
        let isDrawing = false;

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });

        function initializePage() {
            // Get token from URL
            var urlParams = new URLSearchParams(window.location.search);
            currentToken = urlParams.get('token');

            if (!currentToken) {
                showError('Invalid or missing contract link. Please check your email for the correct link.');
                return;
            }

            // Initialize signature pad
            initializeSignaturePad();
            
            // Load contract data
            loadContract();
            
            // Setup event listeners
            setupEventListeners();
        }

        function loadContract() {
            fetch('/sign/api/get-contract.php?token=' + currentToken)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.contract) {
                        contract = data.contract;
                        displayContract();
                        
                        if (contract.isSigned) {
                            showSignedState();
                        }
                    } else {
                        showError(data.error || 'Unable to load contract data.');
                    }
                })
                .catch(function(error) {
                    console.error('Error loading contract:', error);
                    showError('Failed to load contract. Please try again or contact support.');
                });
        }

        function displayContract() {
            // Hide loading, show contract
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('contract-interface').classList.remove('hidden');

            // Populate client information
            document.getElementById('client-name').textContent = contract.clientName || 'N/A';
            document.getElementById('client-email').textContent = contract.clientEmail || 'N/A';
            document.getElementById('client-phone').textContent = contract.clientPhone || 'N/A';
            document.getElementById('client-address').innerHTML = (contract.clientAddress || 'N/A').replace(/\n/g, '<br>');

            // Populate project details
            document.getElementById('quote-number').textContent = contract.quoteNumber || 'N/A';
            document.getElementById('project-type').textContent = contract.projectType || 'N/A';
            document.getElementById('installation-date').textContent = contract.installationDate || 'TBC';
            document.getElementById('total-amount').textContent = '£' + (contract.totalAmount || 0);

            // Populate scope of work
            document.getElementById('scope-of-work').textContent = contract.scopeOfWork || 'Standard installation';

            // Populate line items
            var lineItemsBody = document.getElementById('line-items-body');
            lineItemsBody.innerHTML = '';
            
            if (contract.lineItems && contract.lineItems.length > 0) {
                for (var i = 0; i < contract.lineItems.length; i++) {
                    var item = contract.lineItems[i];
                    var row = document.createElement('tr');
                    var total = (item.quantity || 0) * (item.unitPrice || 0);
                    row.innerHTML = '<td>' + (item.description || 'N/A') + '</td>' +
                                   '<td>' + (item.quantity || 0) + '</td>' +
                                   '<td>£' + (item.unitPrice || 0).toFixed(2) + '</td>' +
                                   '<td>£' + total.toFixed(2) + '</td>';
                    lineItemsBody.appendChild(row);
                }
            } else {
                lineItemsBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #666;">No line items available</td></tr>';
            }

            // Populate payment schedule
            var paymentScheduleBody = document.getElementById('payment-schedule-body');
            paymentScheduleBody.innerHTML = '';
            
            if (contract.paymentSchedule && contract.paymentSchedule.length > 0) {
                for (var i = 0; i < contract.paymentSchedule.length; i++) {
                    var payment = contract.paymentSchedule[i];
                    var row = document.createElement('tr');
                    row.innerHTML = '<td>' + (payment.stage || 'N/A') + '</td>' +
                                   '<td>' + (payment.description || payment.stage || 'N/A') + '</td>' +
                                   '<td>' + (payment.percentage || 0) + '%</td>' +
                                   '<td>£' + (payment.amount || 0).toFixed(2) + '</td>';
                    paymentScheduleBody.appendChild(row);
                }
            } else {
                paymentScheduleBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #666;">No payment schedule available</td></tr>';
            }

            // Populate terms and conditions
            document.getElementById('terms-content').innerHTML = contract.termsContent || 'Standard terms and conditions apply.';
        }

        function initializeSignaturePad() {
            signaturePadCanvas = document.getElementById('signature-canvas');
            signaturePadContext = signaturePadCanvas.getContext('2d');
            signaturePadContext.strokeStyle = '#000';
            signaturePadContext.lineWidth = 2;
            signaturePadContext.lineCap = 'round';
        }

        function setupEventListeners() {
            // Signature type change
            var signatureTypeRadios = document.querySelectorAll('input[name="signature-type"]');
            for (var i = 0; i < signatureTypeRadios.length; i++) {
                signatureTypeRadios[i].addEventListener('change', function() {
                    if (this.value === 'typed') {
                        document.getElementById('typed-signature').classList.remove('hidden');
                        document.getElementById('drawn-signature').classList.add('hidden');
                    } else {
                        document.getElementById('typed-signature').classList.add('hidden');
                        document.getElementById('drawn-signature').classList.remove('hidden');
                    }
                    updateSignButtonState();
                });
            }

            // Terms agreement checkbox
            document.getElementById('terms-agreement').addEventListener('change', updateSignButtonState);

            // Typed name input  
            document.getElementById('typed-name').addEventListener('input', updateSignButtonState);

            // Canvas drawing events
            signaturePadCanvas.addEventListener('mousedown', startDrawing);
            signaturePadCanvas.addEventListener('mousemove', draw);
            signaturePadCanvas.addEventListener('mouseup', stopDrawing);
            signaturePadCanvas.addEventListener('mouseout', stopDrawing);

            // Touch events for mobile
            signaturePadCanvas.addEventListener('touchstart', handleTouch);
            signaturePadCanvas.addEventListener('touchmove', handleTouch);
            signaturePadCanvas.addEventListener('touchend', stopDrawing);

            // Clear signature button
            document.getElementById('clear-signature').addEventListener('click', clearSignature);

            // Sign contract button
            document.getElementById('sign-contract').addEventListener('click', signContract);
        }

        function updateSignButtonState() {
            var termsAgreed = document.getElementById('terms-agreement').checked;
            var signatureType = document.querySelector('input[name="signature-type"]:checked').value;
            var hasSignature = false;

            if (signatureType === 'typed') {
                hasSignature = document.getElementById('typed-name').value.trim().length > 0;
            } else {
                hasSignature = !isCanvasEmpty();
            }

            document.getElementById('sign-contract').disabled = !(termsAgreed && hasSignature);
        }

        function startDrawing(e) {
            isDrawing = true;
            var rect = signaturePadCanvas.getBoundingClientRect();
            signaturePadContext.beginPath();
            signaturePadContext.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }

        function draw(e) {
            if (!isDrawing) return;
            var rect = signaturePadCanvas.getBoundingClientRect();
            signaturePadContext.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            signaturePadContext.stroke();
            updateSignButtonState();
        }

        function stopDrawing() {
            isDrawing = false;
        }

        function handleTouch(e) {
            e.preventDefault();
            var touch = e.touches[0];
            var mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 
                                             e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            signaturePadCanvas.dispatchEvent(mouseEvent);
        }

        function clearSignature() {
            signaturePadContext.clearRect(0, 0, signaturePadCanvas.width, signaturePadCanvas.height);
            updateSignButtonState();
        }

        function isCanvasEmpty() {
            var blank = document.createElement('canvas');
            blank.width = signaturePadCanvas.width;
            blank.height = signaturePadCanvas.height;
            return signaturePadCanvas.toDataURL() === blank.toDataURL();
        }

        function signContract() {
            var signatureType = document.querySelector('input[name="signature-type"]:checked').value;
            var signatureData = '';

            if (signatureType === 'typed') {
                signatureData = document.getElementById('typed-name').value.trim();
            } else {
                signatureData = signaturePadCanvas.toDataURL();
            }

            var submitData = {
                token: currentToken,
                signatureData: signatureData,
                signatureType: signatureType,
                agreesToTerms: document.getElementById('terms-agreement').checked
            };

            // Disable button and show loading
            var signButton = document.getElementById('sign-contract');
            signButton.disabled = true;
            signButton.textContent = 'Signing...';

            fetch('/sign/api/submit-signature.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(submitData)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showSignedState();
                } else {
                    alert('Error signing contract: ' + (data.error || 'Unknown error'));
                    signButton.disabled = false;
                    signButton.textContent = 'Sign Contract';
                }
            })
            .catch(function(error) {
                console.error('Error signing contract:', error);
                alert('Failed to sign contract. Please try again.');
                signButton.disabled = false;
                signButton.textContent = 'Sign Contract';
            });
        }

        function showSignedState() {
            document.getElementById('signature-section').classList.add('hidden');
            document.getElementById('signed-state').classList.remove('hidden');
            document.getElementById('signed-date').textContent = new Date().toLocaleDateString();
        }

        function showError(message) {
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('error-state').classList.remove('hidden');
            document.getElementById('error-message').textContent = message;
        }

        function downloadContract() {
            if (!contract) {
                alert('Contract not available for download');
                return;
            }
            
            // Create a comprehensive contract document
            const contractDocument = `KITCHEN & BATHROOM (NE) LTD - CONTRACT AGREEMENT

════════════════════════════════════════════════════════════════
CONTRACT DETAILS
════════════════════════════════════════════════════════════════

Quote Number: ${contract.quoteNumber || 'N/A'}
Contract Date: ${new Date().toLocaleDateString()}

Client Information:
- Name: ${contract.clientName || 'N/A'}
- Email: ${contract.clientEmail || 'N/A'}
- Address: ${contract.clientAddress || 'N/A'}
- Phone: ${contract.clientPhone || 'N/A'}

Project Details:
- Type: ${contract.projectType || 'General'}
- Installation Date: ${contract.installationDate || 'To be confirmed'}
- Scope of Work: ${contract.scopeOfWork || 'N/A'}

════════════════════════════════════════════════════════════════
LINE ITEMS
════════════════════════════════════════════════════════════════

${contract.lineItems && contract.lineItems.length > 0 
    ? contract.lineItems.map(item => 
        `${item.description || 'Item'}: £${item.unitPrice || 0} (Qty: ${item.quantity || 1})`
    ).join('\n')
    : 'No line items specified'
}

════════════════════════════════════════════════════════════════
PAYMENT SCHEDULE
════════════════════════════════════════════════════════════════

${contract.paymentSchedule && contract.paymentSchedule.length > 0
    ? contract.paymentSchedule.map(payment => 
        `${payment.stage || 'Payment'}: £${payment.amount || 0} (${payment.percentage || 0}%) - ${payment.description || ''}`
    ).join('\n')
    : 'No payment schedule specified'
}

Total Contract Value: £${contract.totalAmount || 0}

════════════════════════════════════════════════════════════════
TERMS AND CONDITIONS
════════════════════════════════════════════════════════════════

${contract.termsContent || 'Terms and conditions not available'}

════════════════════════════════════════════════════════════════
SIGNATURE INFORMATION
════════════════════════════════════════════════════════════════

${contract.isSigned 
    ? `Contract Status: SIGNED
Signed Date: ${contract.signedAt || 'N/A'}
Client Signature: Digitally signed by ${contract.clientName || 'Client'}`
    : `Contract Status: PENDING SIGNATURE
This contract has not yet been signed by the client.`
}

════════════════════════════════════════════════════════════════
COMPANY INFORMATION
════════════════════════════════════════════════════════════════

Kitchen & Bathroom (NE) Ltd
Suite 2 SM Business Centre
Spennymoor, Co Durham DL16 6EL
United Kingdom
Email: info@kitchen-bathroom.co.uk

Document generated: ${new Date().toLocaleString()}
`;

            // Create and download the file
            const blob = new Blob([contractDocument], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `Contract_${contract.quoteNumber || 'Document'}_${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
        
        function printContract() {
            if (!contract) {
                alert('Contract not available for printing');
                return;
            }
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Contract - ${contract.quoteNumber || 'Kitchen & Bathroom (NE) Ltd'}</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 30px; color: #333; }
                        .header { text-align: center; border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
                        .company-name { font-size: 24px; font-weight: bold; color: #2563eb; }
                        .section { margin-bottom: 25px; page-break-inside: avoid; }
                        .section-title { background: #f1f5f9; padding: 10px; font-weight: bold; color: #1e40af; border-left: 4px solid #2563eb; margin-bottom: 15px; }
                        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                        .detail-item { margin-bottom: 8px; }
                        .label { font-weight: bold; color: #374151; }
                        .terms-content { white-space: pre-line; line-height: 1.8; font-size: 14px; }
                        .total-amount { font-size: 18px; font-weight: bold; color: #dc2626; text-align: center; padding: 10px; background: #fef2f2; border: 2px solid #fca5a5; margin: 15px 0; }
                        @media print { body { margin: 15px; font-size: 12px; } .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
                        <button onclick="window.print()" style="background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">🖨️ Print Contract</button>
                        <button onclick="window.close()" style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">✖️ Close</button>
                    </div>
                    
                    <div class="header">
                        <div class="company-name">Kitchen & Bathroom (NE) Ltd</div>
                        <div>Suite 2 SM Business Centre, Spennymoor, Co Durham DL16 6EL</div>
                        <div>Email: info@kitchen-bathroom.co.uk</div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">CONTRACT DETAILS</div>
                        <div class="details-grid">
                            <div>
                                <div class="detail-item"><span class="label">Quote Number:</span> ${contract.quoteNumber || 'N/A'}</div>
                                <div class="detail-item"><span class="label">Project Type:</span> ${contract.projectType || 'General'}</div>
                                <div class="detail-item"><span class="label">Installation Date:</span> ${contract.installationDate || 'To be confirmed'}</div>
                            </div>
                            <div>
                                <div class="detail-item"><span class="label">Client Name:</span> ${contract.clientName || 'N/A'}</div>
                                <div class="detail-item"><span class="label">Client Email:</span> ${contract.clientEmail || 'N/A'}</div>
                                <div class="detail-item"><span class="label">Client Phone:</span> ${contract.clientPhone || 'N/A'}</div>
                            </div>
                        </div>
                        <div class="detail-item"><span class="label">Client Address:</span> ${contract.clientAddress || 'N/A'}</div>
                        <div class="detail-item"><span class="label">Scope of Work:</span> ${contract.scopeOfWork || 'N/A'}</div>
                    </div>
                    
                    ${contract.lineItems && contract.lineItems.length > 0 ? `
                    <div class="section">
                        <div class="section-title">LINE ITEMS</div>
                        ${contract.lineItems.map(item => `
                            <div class="detail-item">
                                <span class="label">${item.description || 'Item'}:</span> 
                                £${item.unitPrice || 0} (Quantity: ${item.quantity || 1})
                            </div>
                        `).join('')}
                    </div>
                    ` : ''}
                    
                    ${contract.paymentSchedule && contract.paymentSchedule.length > 0 ? `
                    <div class="section">
                        <div class="section-title">PAYMENT SCHEDULE</div>
                        ${contract.paymentSchedule.map(payment => `
                            <div class="detail-item">
                                <span class="label">${payment.stage || 'Payment'}:</span> 
                                £${payment.amount || 0} (${payment.percentage || 0}%) - ${payment.description || ''}
                            </div>
                        `).join('')}
                    </div>
                    ` : ''}
                    
                    <div class="total-amount">
                        TOTAL CONTRACT VALUE: £${contract.totalAmount || 0}
                    </div>
                    
                    <div class="section">
                        <div class="section-title">TERMS AND CONDITIONS</div>
                        <div class="terms-content">${contract.termsContent || 'Terms and conditions not available'}</div>
                    </div>
                    
                    <div style="margin-top: 40px; padding: 20px; border: 2px solid #10b981; background: #f0f9ff;">
                        <div class="section-title">SIGNATURE INFORMATION</div>
                        ${contract.isSigned 
                            ? `<div><strong>Status:</strong> ✅ SIGNED</div>
                               <div><strong>Signed Date:</strong> ${contract.signedAt || 'N/A'}</div>
                               <div><strong>Client Signature:</strong> Digitally signed by ${contract.clientName || 'Client'}</div>`
                            : `<div><strong>Status:</strong> ⏳ PENDING SIGNATURE</div>
                               <div>This contract has not yet been signed by the client.</div>`
                        }
                        <div style="margin-top: 15px;"><strong>Document Generated:</strong> ${new Date().toLocaleString()}</div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            
            // Auto-print after a short delay
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }

        function printTermsOnly() {
            if (!contract || !contract.termsContent) {
                alert('Terms and conditions not available for printing');
                return;
            }
            
            const termsWindow = window.open('', '_blank');
            termsWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Terms and Conditions - Kitchen & Bathroom (NE) Ltd</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; color: #333; }
                        h1 { color: #2563eb; text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
                        .company-info { text-align: center; margin-bottom: 30px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; }
                        .content { white-space: pre-line; line-height: 1.8; }
                        .print-info { text-align: center; margin-top: 30px; padding: 15px; background: #f0f9ff; border: 1px solid #0ea5e9; font-size: 0.9em; }
                        @media print { body { margin: 20px; } .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
                        <button onclick="window.print()" style="background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">🖨️ Print</button>
                        <button onclick="window.close()" style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">✖️ Close</button>
                    </div>
                    
                    <h1>Terms and Conditions</h1>
                    
                    <div class="company-info">
                        <strong>Kitchen & Bathroom (NE) Ltd</strong><br>
                        Suite 2 SM Business Centre, Spennymoor, Co Durham DL16 6EL<br>
                        Email: info@kitchen-bathroom.co.uk
                    </div>
                    
                    <div class="content">${contract.termsContent}</div>
                    
                    <div class="print-info">
                        <strong>Document Information:</strong><br>
                        Generated: ${new Date().toLocaleString()}<br>
                        Contract Reference: ${contract.quoteNumber || 'N/A'}<br>
                        Client: ${contract.clientName || 'N/A'}
                    </div>
                </body>
                </html>
            `);
            termsWindow.document.close();
            
            // Auto-print after a short delay
            setTimeout(() => {
                termsWindow.print();
            }, 500);
        }
        
        function downloadTermsOnly() {
            if (!contract || !contract.termsContent) {
                alert('Terms and conditions not available for download');
                return;
            }
            
            // Create a comprehensive terms document
            const termsDocument = `TERMS AND CONDITIONS
Kitchen & Bathroom (NE) Ltd
Suite 2 SM Business Centre, Spennymoor, Co Durham DL16 6EL
Email: info@kitchen-bathroom.co.uk

Generated: ${new Date().toLocaleString()}
Contract Reference: ${contract.quoteNumber || 'N/A'}
Client: ${contract.clientName || 'N/A'}

═══════════════════════════════════════════════════════════════

${contract.termsContent}

═══════════════════════════════════════════════════════════════

Document Information:
- Generated on: ${new Date().toLocaleString()}
- For contract: ${contract.quoteNumber || 'N/A'}
- Client name: ${contract.clientName || 'N/A'}
- Total contract value: £${contract.totalAmount || '0'}

This document contains the complete terms and conditions that apply to your contract with Kitchen & Bathroom (NE) Ltd.

For any questions or clarifications, please contact:
Email: info@kitchen-bathroom.co.uk
`;

            // Create and download the file
            const blob = new Blob([termsDocument], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `Terms_and_Conditions_${contract.quoteNumber || 'Contract'}_${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    </script>
</body>
</html>