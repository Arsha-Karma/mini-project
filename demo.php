
        <!-- OTP Verification Form -->
        <form method="post" action="" id="otp-form" class="otp-form <?php echo $showOTPForm ? 'active' : ''; ?>">
            <h2>Verify Email</h2>
            
            <?php if (isset($otpMessage)): ?>
                <div class="success">
                    <?php echo htmlspecialchars($otpMessage); ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Enter OTP sent to your email</label>
                <input type="text" name="otp" class="otp-input" maxlength="6" pattern="\d{6}" 
                       title="Please enter 6 digits" placeholder="Enter 6-digit OTP" required>
                <div class="helper-text">OTP will expire in 15 minutes</div>
            </div>
            
            <button type="submit" name="verify_otp" class="submit-btn">Verify OTP</button>
        </form>
    </div>