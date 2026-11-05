
import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import exec from 'k6/execution';

const duplicates = new Counter('duplicate_requests');
const created = new Counter('created_submissions');
const errors = new Counter('request_errors');

export const options = {
    scenarios: {
        submission_test: {
            executor: 'shared-iterations',
            vus: 50,
            iterations: 100000,
            maxDuration: '30m',
        },
    },
};


export default function () {
    const requestNumber = exec.scenario.iterationInTest;
    // First 80,000 keys are unique; remaining 20,000 reuse keys to test duplication handling
    const keyNumber = requestNumber < 80000 ? requestNumber : requestNumber - 80000;

    const payload = JSON.stringify({
        form_id: 'contact-form',
        idempotency_key: 'key-' + keyNumber,
        email: 'user@example.com',
        data: {
            name: 'John Doe',
            message: 'Load testing submission stream'
        }
    });

    const response = http.post('http://localhost:8000/submissions', payload, {
        headers: { 'Content-Type': 'application/json' },
        timeout: '30s',
    });

    const validStatus = response.status === 200 || response.status === 201;
    let body = {};
    
    try {
        body = response.json();
    } catch (e) {
        // Handle parsing errors gracefully
    }

    if (!validStatus || body.success !== true) {
        errors.add(1);
    } else if (body.duplicate === true) {
        duplicates.add(1);
    } else {
        created.add(1);
    }

    check(response, {
        'status is 200 or 201': () => validStatus,
        'json success true': () => body.success === true,
    });
}