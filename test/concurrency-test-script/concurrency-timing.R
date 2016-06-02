
# data collection via...
# (n=0; export postgres_bloat_rows=${n} postgres_bloat_vacuum=1; echo "next.time" > /tmp/next.log; sh concurrency-test.sh; wc -l /tmp/next.log /tmp/jqjobs-concurrency.log; mv /tmp/next.log /tmp/next-${postgres_bloat_rows}-bloat-vacuum-${postgres_bloat_vacuum}.log)
reports <- c(
"/tmp/enqueue-coalesceid-off.log",
"/tmp/next-coalesceid-off.log",
"/tmp/enqueue-coalesceid-on.log",
"/tmp/next-coalesceid-on.log",
"/tmp/enqueue+bloat-coalesceid-on.log",
"/tmp/next+bloat-coalesceid-on.log"
)

par(mfrow=c(ceiling(length(reports)/2),2))

for (n in reports) {
	dataFile =  as.character(n)
	data <- read.csv(dataFile)
	density <- density(data$next.time, from=0,to=10)
	avgSecs = mean(data$next.time)
	xaxisLabel <- paste("(seconds", avgSecs, " avg", ")", "n:", density['n'], "bandwidth:", density['bw'])
	plot(density, main=dataFile, xlab=xaxisLabel)
}