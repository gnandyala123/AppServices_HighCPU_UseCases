package com.azure.demo.highcpu.listeners;

import com.azure.demo.highcpu.events.CpuStressCompletedEvent;
import com.azure.demo.highcpu.events.CpuStressEvent;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.atomic.AtomicLong;
import java.util.logging.Logger;

/**
 * Delegate/Listener that reacts to CpuStressEvent.
 * Spawns multiple threads performing CPU-heavy computation
 * to simulate high CPU usage on Azure App Service.
 */
@Component
public class CpuStressListener {

    private static final Logger logger = Logger.getLogger(CpuStressListener.class.getName());
    private final ApplicationEventPublisher eventPublisher;

    public CpuStressListener(ApplicationEventPublisher eventPublisher) {
        this.eventPublisher = eventPublisher;
    }

    @EventListener
    public void handleCpuStressEvent(CpuStressEvent event) {
        int threadCount = event.getThreadCount();
        int durationSeconds = event.getDurationSeconds();

        logger.info("CpuStressListener received event: spawning " + threadCount
                + " threads for " + durationSeconds + " seconds");

        long startTime = System.currentTimeMillis();
        AtomicLong totalIterations = new AtomicLong(0);
        long deadline = startTime + (durationSeconds * 1000L);

        List<Thread> threads = new ArrayList<>();

        for (int i = 0; i < threadCount; i++) {
            final int threadId = i;
            Thread worker = Thread.ofPlatform().name("cpu-stress-" + threadId).start(() -> {
                logger.info("Thread cpu-stress-" + threadId + " started CPU-intensive work");
                long localCount = 0;

                // Tight computational loop: repeated math operations to burn CPU
                while (System.currentTimeMillis() < deadline) {
                    double result = 0;
                    for (int j = 0; j < 1_000_000; j++) {
                        result += Math.sqrt(j) * Math.sin(j) * Math.cos(j);
                    }
                    localCount++;
                    // Prevent JIT from optimizing away the computation
                    if (result == Double.MAX_VALUE) {
                        logger.fine("Unreachable: " + result);
                    }
                }

                totalIterations.addAndGet(localCount);
                logger.info("Thread cpu-stress-" + threadId + " completed: " + localCount + " iterations");
            });
            threads.add(worker);
        }

        // Wait for all threads to finish
        for (Thread t : threads) {
            try {
                t.join();
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
                logger.warning("Interrupted while waiting for thread: " + t.getName());
            }
        }

        long elapsed = System.currentTimeMillis() - startTime;
        logger.info("All CPU stress threads completed in " + elapsed + " ms, total iterations: " + totalIterations.get());

        // Publish completion event so other delegates can react
        eventPublisher.publishEvent(
                new CpuStressCompletedEvent(this, threadCount, totalIterations.get(), elapsed));
    }
}
