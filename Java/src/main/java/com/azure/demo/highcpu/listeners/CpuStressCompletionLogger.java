package com.azure.demo.highcpu.listeners;

import com.azure.demo.highcpu.events.CpuStressCompletedEvent;
import org.springframework.context.event.EventListener;
import org.springframework.stereotype.Component;

import java.util.logging.Logger;

/**
 * A second delegate that listens for the completion event.
 * Demonstrates chaining in the event/delegate model.
 */
@Component
public class CpuStressCompletionLogger {

    private static final Logger logger = Logger.getLogger(CpuStressCompletionLogger.class.getName());

    @EventListener
    public void onStressCompleted(CpuStressCompletedEvent event) {
        logger.info("=== CPU Stress Completed ===");
        logger.info("Threads used     : " + event.getThreadCount());
        logger.info("Total iterations : " + event.getTotalIterations());
        logger.info("Elapsed (ms)     : " + event.getElapsedMillis());
        logger.info("============================");
    }
}
